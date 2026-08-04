<?php
// PDO/SQLite storage for booking requests. No external DB service required —
// works on standard shared PHP hosting (pdo_sqlite ships with PHP by default).

// BLAYA is a small-capacity kennel — the site's own FAQ states a hard cap of
// 4 dogs at a time. Used to flag over-booked days in the calendar/detail view.
const MAX_DAILY_DOGS = 4;

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dbPath = __DIR__ . '/../data/bookings.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at      TEXT NOT NULL,
            status          TEXT NOT NULL DEFAULT 'pending',
            owner_name      TEXT,
            owner_phone     TEXT,
            owner_email     TEXT,
            owner_address   TEXT,
            dog_name        TEXT,
            dogs_count      INTEGER,
            date_from       TEXT,
            date_to         TEXT,
            total           INTEGER,
            deposit         INTEGER,
            remainder       INTEGER,
            payment_method  TEXT,
            payment_timing  TEXT,
            packages_raw    TEXT,
            raw_json        TEXT
        )
    ");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_bookings_dates ON bookings(date_from, date_to)');

    // Lightweight migration: add columns introduced after the initial launch
    // without wiping existing rows.
    $existingCols = [];
    foreach ($pdo->query('PRAGMA table_info(bookings)') as $col) {
        $existingCols[] = $col['name'];
    }
    if (!in_array('attachments_json', $existingCols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN attachments_json TEXT');
    }

    return $pdo;
}

// $fields is the associative array of already-sanitized values from send.php.
// Returns the new row id, or false on failure (never throws — a storage
// hiccup must not break the existing email-sending flow in send.php).
function saveBooking(array $fields) {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("
            INSERT INTO bookings (
                created_at, status, owner_name, owner_phone, owner_email, owner_address,
                dog_name, dogs_count, date_from, date_to, total, deposit, remainder,
                payment_method, payment_timing, packages_raw, raw_json
            ) VALUES (
                :created_at, 'pending', :owner_name, :owner_phone, :owner_email, :owner_address,
                :dog_name, :dogs_count, :date_from, :date_to, :total, :deposit, :remainder,
                :payment_method, :payment_timing, :packages_raw, :raw_json
            )
        ");
        $stmt->execute([
            ':created_at'     => gmdate('c'),
            ':owner_name'     => $fields['owner_name'] ?? '',
            ':owner_phone'    => $fields['owner_phone'] ?? '',
            ':owner_email'    => $fields['owner_email'] ?? '',
            ':owner_address'  => $fields['owner_address'] ?? '',
            ':dog_name'       => $fields['dog_name'] ?? '',
            ':dogs_count'     => $fields['dogs_count'] ?? 1,
            ':date_from'      => $fields['date_from'] ?? '',
            ':date_to'        => $fields['date_to'] ?? '',
            ':total'          => $fields['total'] ?? 0,
            ':deposit'        => $fields['deposit'] ?? 0,
            ':remainder'      => $fields['remainder'] ?? 0,
            ':payment_method' => $fields['payment_method'] ?? '',
            ':payment_timing' => $fields['payment_timing'] ?? '',
            ':packages_raw'   => $fields['packages_raw'] ?? '',
            ':raw_json'       => json_encode($fields['raw'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('BLAYA saveBooking failed: ' . $e->getMessage());
        return false;
    }
}

// $status: null for all, or 'pending'|'accepted'|'declined'
function getBookings(?string $status = null): array {
    $pdo = getDb();
    if ($status !== null) {
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE status = :status ORDER BY created_at DESC');
        $stmt->execute([':status' => $status]);
    } else {
        $stmt = $pdo->query('SELECT * FROM bookings ORDER BY created_at DESC');
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBooking(int $id): ?array {
    $pdo = getDb();
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function updateBookingStatus(int $id, string $status): bool {
    if (!in_array($status, ['pending', 'accepted', 'declined'], true)) return false;
    $pdo = getDb();
    $stmt = $pdo->prepare('UPDATE bookings SET status = :status WHERE id = :id');
    return $stmt->execute([':status' => $status, ':id' => $id]);
}

function deleteBooking(int $id): bool {
    $pdo = getDb();
    $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}

// Total accepted dogs per calendar day within [rangeStart, rangeEnd]
// (YYYY-MM-DD, inclusive) — e.g. ['2026-08-20' => 3, '2026-08-21' => 4].
// $excludeId lets a booking's own detail page check capacity against
// *other* bookings only (so it doesn't flag itself as a conflict).
function getAcceptedDogCounts(string $rangeStart, string $rangeEnd, ?int $excludeId = null): array {
    $pdo = getDb();
    $sql = "
        SELECT * FROM bookings
        WHERE status = 'accepted'
          AND date_from <> ''
          AND date_from <= :rangeEnd
          AND (date_to >= :rangeStart OR date_to = '' OR date_to = 'Nincs megadva')
    ";
    $params = [':rangeStart' => $rangeStart, ':rangeEnd' => $rangeEnd];
    if ($excludeId !== null) {
        $sql .= ' AND id <> :excludeId';
        $params[':excludeId'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rangeStartDt = DateTime::createFromFormat('Y-m-d', $rangeStart);
    $rangeEndDt = DateTime::createFromFormat('Y-m-d', $rangeEnd);
    $counts = [];
    foreach ($rows as $b) {
        $from = DateTime::createFromFormat('Y-m-d', $b['date_from']);
        if (!$from) continue;
        $to = DateTime::createFromFormat('Y-m-d', $b['date_to']);
        if (!$to || $to < $from) $to = clone $from;

        $cursor = max($from, $rangeStartDt);
        $stop = min($to, $rangeEndDt);
        $dogs = max(1, (int)$b['dogs_count']);
        while ($cursor <= $stop) {
            $key = $cursor->format('Y-m-d');
            $counts[$key] = ($counts[$key] ?? 0) + $dogs;
            $cursor->modify('+1 day');
        }
    }
    return $counts;
}

// Bookings of a given status whose stay overlaps [rangeStart, rangeEnd]
// (YYYY-MM-DD, inclusive) — used by the calendar view.
function getBookingsInRangeByStatus(string $status, string $rangeStart, string $rangeEnd): array {
    $pdo = getDb();
    $stmt = $pdo->prepare("
        SELECT * FROM bookings
        WHERE status = :status
          AND date_from <> ''
          AND date_from <= :rangeEnd
          AND (date_to >= :rangeStart OR date_to = '' OR date_to = 'Nincs megadva')
        ORDER BY date_from ASC
    ");
    $stmt->execute([':status' => $status, ':rangeStart' => $rangeStart, ':rangeEnd' => $rangeEnd]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Kept for backward compatibility with existing call sites.
function getAcceptedBookingsInRange(string $rangeStart, string $rangeEnd): array {
    return getBookingsInRangeByStatus('accepted', $rangeStart, $rangeEnd);
}

// ─── Booklet / photo attachments ───
// Stored on disk under admin/data/uploads/{booking id}/ — that directory
// inherits admin/data/.htaccess's "deny all", so files are only reachable
// through admin/attachment.php after login.

function bookingUploadsDir(int $bookingId): string {
    return __DIR__ . '/../data/uploads/' . $bookingId;
}

function bookingAttachmentPath(int $bookingId, string $storedName): string {
    return bookingUploadsDir($bookingId) . '/' . $storedName;
}

// Moves one already-uploaded file ($tmpPath, from $_FILES) into permanent
// storage for $bookingId. Returns the stored filename, or null on failure —
// never throws, since a failed save must not break the booking email flow.
function saveBookingAttachmentFile(int $bookingId, string $tmpPath, string $originalName): ?string {
    $dir = bookingUploadsDir($bookingId);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $storedName = uniqid('', true) . ($safeBase !== '' ? '_' . $safeBase : '') . ($ext !== '' ? '.' . $ext : '');
    $dest = $dir . '/' . $storedName;

    $moved = is_uploaded_file($tmpPath) ? move_uploaded_file($tmpPath, $dest) : copy($tmpPath, $dest);
    return $moved ? $storedName : null;
}

// $files: list of ['field','name','stored','type','size'] — see send.php.
function saveBookingAttachments(int $bookingId, array $files): bool {
    $pdo = getDb();
    $stmt = $pdo->prepare('UPDATE bookings SET attachments_json = :json WHERE id = :id');
    return $stmt->execute([':json' => json_encode($files, JSON_UNESCAPED_UNICODE), ':id' => $bookingId]);
}

// ─── Dashboard stats ───
// Bookings/dogs/income grouped by status. Pass $rangeStart/$rangeEnd
// (YYYY-MM-DD) to scope to bookings whose stay *starts* in that window —
// same field the calendar itself is built on, so "this month" always means
// the same thing across the admin. Pass both as null for all-time totals.
function getStatsForRange(?string $rangeStart, ?string $rangeEnd): array {
    $pdo = getDb();
    $where = '';
    $params = [];
    if ($rangeStart !== null && $rangeEnd !== null) {
        $where = 'WHERE date_from >= :rangeStart AND date_from <= :rangeEnd';
        $params = [':rangeStart' => $rangeStart, ':rangeEnd' => $rangeEnd];
    }
    $stmt = $pdo->prepare("
        SELECT status,
               COUNT(*) AS bookings,
               COALESCE(SUM(dogs_count), 0) AS dogs,
               COALESCE(SUM(total), 0) AS total
        FROM bookings
        {$where}
        GROUP BY status
    ");
    $stmt->execute($params);

    $byStatus = [
        'accepted' => ['bookings' => 0, 'dogs' => 0, 'total' => 0],
        'declined' => ['bookings' => 0, 'dogs' => 0, 'total' => 0],
        'pending'  => ['bookings' => 0, 'dogs' => 0, 'total' => 0],
    ];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($byStatus[$row['status']])) {
            $byStatus[$row['status']] = [
                'bookings' => (int)$row['bookings'],
                'dogs'     => (int)$row['dogs'],
                'total'    => (int)$row['total'],
            ];
        }
    }

    $decided = $byStatus['accepted']['bookings'] + $byStatus['declined']['bookings'];

    return [
        'accepted'       => $byStatus['accepted'],
        'declined'       => $byStatus['declined'],
        'pending'        => $byStatus['pending'],
        'requestsCount'  => $byStatus['accepted']['bookings'] + $byStatus['declined']['bookings'] + $byStatus['pending']['bookings'],
        'acceptanceRate' => $decided > 0 ? round($byStatus['accepted']['bookings'] / $decided * 100) : null,
    ];
}
