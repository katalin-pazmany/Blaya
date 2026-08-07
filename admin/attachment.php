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

// Never trust the stored 'type' for inline rendering — it may be a forged
// client-supplied value (or a legacy row saved before upload validation
// existed). Re-sniff the actual file content and only serve it inline as
// one of the whitelisted safe types; anything else downloads as a generic
// blob instead of risking the browser rendering it as HTML in this
// logged-in admin session.
$safeName = basename($match['name'] ?: $file);
$safeType = detectSafeUploadType($path, $match['name'] ?: $file);
if ($safeType !== null) {
    header('Content-Type: ' . $safeType);
    header('Content-Disposition: inline; filename="' . addslashes($safeName) . '"');
} else {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($safeName) . '"');
}
// Belt-and-braces: stops a browser from ever content-sniffing this response
// into something more dangerous than the Content-Type set above.
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, no-cache');
readfile($path);
