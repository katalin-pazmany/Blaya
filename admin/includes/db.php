<?php
// PDO/SQLite storage for booking requests. No external DB service required —
// works on standard shared PHP hosting (pdo_sqlite ships with PHP by default).

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

// Accepted bookings whose stay overlaps the given [rangeStart, rangeEnd] date
// strings (YYYY-MM-DD, inclusive) — used by the calendar view.
function getAcceptedBookingsInRange(string $rangeStart, string $rangeEnd): array {
    $pdo = getDb();
    $stmt = $pdo->prepare("
        SELECT * FROM bookings
        WHERE status = 'accepted'
          AND date_from <> ''
          AND date_from <= :rangeEnd
          AND (date_to >= :rangeStart OR date_to = '' OR date_to = 'Nincs megadva')
        ORDER BY date_from ASC
    ");
    $stmt->execute([':rangeStart' => $rangeStart, ':rangeEnd' => $rangeEnd]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
