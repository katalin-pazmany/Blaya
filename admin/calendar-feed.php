<?php
// Public iCalendar (.ics) subscription feed — lets Bernadett add BLAYA's
// accepted bookings to her phone's own calendar app (iOS/Google/Outlook all
// support "subscribe to URL"), auto-refreshing instead of manual re-typing.
//
// Deliberately NOT behind require_login(): calendar apps fetch this on their
// own schedule with no session/cookies, so it's protected by a long random
// token in the URL instead (see admin_config()['ics_feed_token']).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$cfg = admin_config();
$expected = $cfg['ics_feed_token'] ?? '';
$given = $_GET['token'] ?? '';
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden.\n";
    exit;
}

// Only confirmed stays go on the calendar — pending/declined requests would
// just be noise (and could still change) on a phone's personal calendar.
$bookings = getBookings('accepted');

function icsEscape(string $s): string {
    return str_replace(["\\", "\n", ",", ";"], ["\\\\", "\\n", "\\,", "\\;"], $s);
}

function icsFold(string $line): string {
    // RFC 5545: lines >75 octets must be folded with CRLF + a leading space.
    $out = '';
    while (strlen($line) > 75) {
        $out .= substr($line, 0, 75) . "\r\n ";
        $line = substr($line, 75);
    }
    return $out . $line;
}

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: inline; filename="blaya-foglalasok.ics"');

$lines = [];
$lines[] = 'BEGIN:VCALENDAR';
$lines[] = 'VERSION:2.0';
$lines[] = 'PRODID:-//BLAYA//Foglalasok//HU';
$lines[] = 'CALSCALE:GREGORIAN';
$lines[] = 'METHOD:PUBLISH';
$lines[] = 'X-WR-CALNAME:' . icsEscape('BLAYA foglalások');
$lines[] = 'X-WR-TIMEZONE:Europe/Budapest';
// Ask subscribing apps to check for updates periodically (support varies,
// but Apple/Google/Outlook all honor this to some degree).
$lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT6H';
$lines[] = 'X-PUBLISHED-TTL:PT6H';

foreach ($bookings as $b) {
    $from = DateTime::createFromFormat('Y-m-d', $b['date_from']);
    if (!$from) continue;
    $to = DateTime::createFromFormat('Y-m-d', $b['date_to']);
    if (!$to || $to < $from) $to = clone $from;
    // All-day events: DTEND is exclusive in iCalendar, so add one day.
    $dtEnd = (clone $to)->modify('+1 day');

    $dogLabel = $b['dog_name'] ?: 'Kutya';
    $summary = $dogLabel . ($b['dogs_count'] > 1 ? ' (' . (int)$b['dogs_count'] . ' kutya)' : '') . ' — ' . ($b['owner_name'] ?: 'Ismeretlen gazda');

    $descParts = [];
    if ($b['owner_phone']) $descParts[] = 'Tel: ' . $b['owner_phone'];
    if ($b['owner_email']) $descParts[] = 'Email: ' . $b['owner_email'];
    if ($b['total']) $descParts[] = 'Összeg: ' . number_format((float)$b['total'], 0, ',', ' ') . ' Ft';
    $description = implode("\n", $descParts);

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:blaya-booking-' . (int)$b['id'] . '@blaya-admin';
    $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
    $lines[] = 'DTSTART;VALUE=DATE:' . $from->format('Ymd');
    $lines[] = 'DTEND;VALUE=DATE:' . $dtEnd->format('Ymd');
    $lines[] = icsFold('SUMMARY:' . icsEscape($summary));
    if ($description !== '') $lines[] = icsFold('DESCRIPTION:' . icsEscape($description));
    $lines[] = 'STATUS:CONFIRMED';
    $lines[] = 'TRANSP:OPAQUE';
    $lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';

echo implode("\r\n", $lines) . "\r\n";
