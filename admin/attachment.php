<?php
// Serves one stored booklet/photo file for a booking. admin/data/ is
// blocked from direct web access (see admin/data/.htaccess) — this is the
// only way to reach an uploaded file, and only after logging in, and only
// a file that's actually listed in that booking's own attachments_json
// (so one booking's URL can't be tweaked to fetch another's documents).
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$file = basename($_GET['file'] ?? '');
$booking = $id ? getBooking($id) : null;
if (!$booking) {
    http_response_code(404);
    exit('Nincs ilyen foglalás.');
}

$attachments = json_decode($booking['attachments_json'] ?? '[]', true) ?: [];
$match = null;
foreach ($attachments as $a) {
    if (($a['stored'] ?? '') === $file) { $match = $a; break; }
}
if (!$match) {
    http_response_code(404);
    exit('Nincs ilyen melléklet.');
}

$path = bookingAttachmentPath($id, $match['stored']);
if (!is_file($path)) {
    http_response_code(404);
    exit('A fájl nem található a szerveren.');
}

header('Content-Type: ' . ($match['type'] ?: 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . addslashes(basename($match['name'] ?: $file)) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, no-cache');
readfile($path);
