<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        die('Érvénytelen kérés.');
    }
    $postId = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($postId && $action === 'delete') {
        deleteBooking($postId);
        header('Location: bookings.php');
        exit;
    }
    $newStatus = ['accept' => 'accepted', 'decline' => 'declined'][$action] ?? null;
    if ($postId && $newStatus) {
        updateBookingStatus($postId, $newStatus);
    }
    header('Location: booking.php?id=' . $postId);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$booking = $id ? getBooking($id) : null;

if (!$booking) {
    http_response_code(404);
    $pageTitle = 'Nincs ilyen foglalás';
    $activeNav = 'bookings';
    require __DIR__ . '/includes/layout_header.php';
    echo '<div class="card empty-state">Ez a foglalás nem található.</div>';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

$raw = json_decode($booking['raw_json'] ?? '[]', true) ?: [];
$attachments = json_decode($booking['attachments_json'] ?? '[]', true) ?: [];
$imageExts = ['jpg', 'jpeg', 'png'];
$csrf = csrf_token();
$statusLabels = ['pending' => 'Függőben', 'accepted' => 'Elfogadva', 'declined' => 'Elutasítva'];

// Flag any day in this booking's own date range that's already at/over the
// kennel's 4-dog capacity from *other* accepted bookings — helps decide
// whether to accept a pending request.
$fullDays = [];
$rangeFrom = DateTime::createFromFormat('Y-m-d', $booking['date_from']);
if ($rangeFrom) {
    $rangeTo = DateTime::createFromFormat('Y-m-d', $booking['date_to']);
    if (!$rangeTo || $rangeTo < $rangeFrom) $rangeTo = clone $rangeFrom;
    $dayCounts = getAcceptedDogCounts($rangeFrom->format('Y-m-d'), $rangeTo->format('Y-m-d'), (int)$booking['id']);
    foreach ($dayCounts as $date => $count) {
        if ($count >= MAX_DAILY_DOGS) $fullDays[] = $date;
    }
    sort($fullDays);
}

function row(string $label, $value): void {
    if ($value === null || $value === '') return;
    echo '<tr><td class="dl-label">' . htmlspecialchars($label) . '</td><td>' . $value . '</td></tr>';
}
function sec(string $title): void {
    echo '<tr><td colspan="2" class="dl-sec">' . htmlspecialchars($title) . '</td></tr>';
}
function warn(string $text): string {
    return '<span class="dl-warn">⚠ ' . htmlspecialchars($text) . '</span>';
}

$pageTitle = $booking['dog_name'] ?: 'Foglalás';
$activeNav = 'bookings';
require __DIR__ . '/includes/layout_header.php';
?>
<style>
.dl-back { display: inline-block; margin-bottom: 16px; font-family: 'Oswald', sans-serif; font-size: 12px; letter-spacing: 1px; color: var(--text-muted); text-decoration: none; }
.dl-back:hover { color: var(--primary); }
.dl-table { width: 100%; border-collapse: collapse; }
.dl-table td { padding: 9px 20px; border-bottom: 1px solid var(--hairline); font-size: 14px; vertical-align: top; }
.dl-label { color: var(--text-muted); font-weight: 600; width: 220px; white-space: nowrap; }
.dl-sec { padding-top: 20px !important; font-family: 'Oswald', sans-serif; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: var(--primary); border-bottom: 2px solid var(--primary) !important; }
.dl-warn { color: #ff6b6b; font-weight: 700; }
.dl-actions { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
.dl-attach-title { font-family: 'Oswald', sans-serif; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: var(--primary); margin-bottom: 14px; }
.dl-attach-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.dl-attach-thumb { display: block; width: 88px; height: 88px; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--hairline); }
.dl-attach-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.dl-attach-file { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.05); border: 1px solid var(--hairline); border-radius: var(--radius-sm); padding: 10px 14px; font-size: 13px; color: var(--text-primary); text-decoration: none; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dl-attach-file:hover, .dl-attach-thumb:hover { border-color: var(--primary); }
</style>

<a class="dl-back" href="bookings.php">&larr; Vissza a foglalásokhoz</a>

<div class="page-head">
  <h1><?= htmlspecialchars($booking['dog_name'] ?: 'Foglalás') ?></h1>
  <span class="badge badge-<?= htmlspecialchars($booking['status']) ?>"><?= htmlspecialchars($statusLabels[$booking['status']] ?? $booking['status']) ?></span>
</div>

<?php if (!empty($fullDays)): ?>
<div class="alert">
  ⚠ Kapacitás figyelmeztetés: <?= implode(', ', array_map(function ($d) { return htmlspecialchars($d); }, $fullDays)) ?>
  napo<?= count($fullDays) > 1 ? 'kon' : 'n' ?> már <?= MAX_DAILY_DOGS ?> kutya van elfogadva — ezt figyelembe kell venni elfogadás előtt.
</div>
<?php endif; ?>

<?php if (!empty($attachments)): ?>
<div class="card" style="padding: 16px 20px; margin-bottom: 20px;">
  <div class="dl-attach-title">Kiskönyv / oltási dokumentáció, fotók</div>
  <div class="dl-attach-grid">
    <?php foreach ($attachments as $a):
      $ext = strtolower(pathinfo($a['name'] ?? '', PATHINFO_EXTENSION));
      $href = 'attachment.php?id=' . (int)$booking['id'] . '&file=' . urlencode($a['stored'] ?? '');
    ?>
      <?php if (in_array($ext, $imageExts, true)): ?>
        <a class="dl-attach-thumb" href="<?= htmlspecialchars($href) ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($a['name'] ?? '') ?>">
          <img src="<?= htmlspecialchars($href) ?>" alt="<?= htmlspecialchars($a['name'] ?? '') ?>" loading="lazy">
        </a>
      <?php else: ?>
        <a class="dl-attach-file" href="<?= htmlspecialchars($href) ?>" target="_blank" rel="noopener">
          📄 <?= htmlspecialchars($a['name'] ?? 'Dokumentum') ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card" style="padding: 8px 12px; margin-bottom: 20px;">
<table class="dl-table">
  <?php sec('Gazda adatai'); ?>
  <?php row('Teljes név:', htmlspecialchars($booking['owner_name'])); ?>
  <?php row('Telefonszám:', htmlspecialchars($booking['owner_phone'])); ?>
  <?php row('Email:', htmlspecialchars($booking['owner_email'])); ?>
  <?php row('Lakcím:', htmlspecialchars($booking['owner_address'])); ?>

  <?php sec('Vészhelyzeti kapcsolattartó'); ?>
  <?php row('Kapcsolattartó neve:', htmlspecialchars($raw['emergencyName'] ?? '')); ?>
  <?php row('Kapcsolattartó telefonszáma:', htmlspecialchars($raw['emergencyPhone'] ?? '')); ?>
  <?php row('Kapcsolat a gazdával:', htmlspecialchars($raw['emergencyRelation'] ?? '')); ?>

  <?php sec($booking['dogs_count'] > 1 ? ($booking['dog_name'] . ' adatai (1. kutya)') : 'A kutya adatai'); ?>
  <?php row('Kutya neve:', htmlspecialchars($booking['dog_name'])); ?>
  <?php row('Fajta:', htmlspecialchars($raw['dogBreed'] ?? '')); ?>
  <?php row('Kor:', htmlspecialchars($raw['dogAge'] ?? '')); ?>
  <?php row('Nem:', htmlspecialchars($raw['dogGender'] ?? '')); ?>
  <?php row('Ivartalanított:', htmlspecialchars($raw['dogNeutered'] ?? '')); ?>
  <?php row('Tüzelő szuka:', htmlspecialchars($raw['dogHeat'] ?? '')); ?>
  <?php row('Chip száma:', htmlspecialchars($raw['dogChip'] ?? '')); ?>
  <?php row('Mióta van a kutya:', htmlspecialchars($raw['dogSince'] ?? '')); ?>
  <?php row('Etetés típusa:', htmlspecialchars($raw['dogFood'] ?? '')); ?>
  <?php row('Napi adag:', !empty($raw['dogFoodAmt']) ? htmlspecialchars($raw['dogFoodAmt']) . ' g' : ''); ?>

  <?php sec('Egészségügyi adatok'); ?>
  <?php row('Veszettség oltás:', !empty($raw['rabiesFlagMain']) ? warn(($raw['vaccRabies'] ?? '') . ' — 14 napon belüli oltás!') : htmlspecialchars($raw['vaccRabies'] ?? '')); ?>
  <?php row('Kombinált oltás:', htmlspecialchars($raw['vaccCombo'] ?? '')); ?>
  <?php row('Utolsó féreghajtó neve:', htmlspecialchars($raw['wormerName'] ?? '')); ?>
  <?php row('Utolsó féreghajtó dátuma:', !empty($raw['wormerFlagMain']) ? warn(($raw['wormerDate'] ?? '') . ' — 3 hónapnál régebbi!') : htmlspecialchars($raw['wormerDate'] ?? '')); ?>
  <?php row('Betegség:', htmlspecialchars($raw['dogIllness'] ?? '')); ?>
  <?php row('Gyógyszer:', htmlspecialchars($raw['dogMeds'] ?? '')); ?>
  <?php row('Trauma/Támadás:', htmlspecialchars($raw['dogTrauma'] ?? '')); ?>

  <?php sec('Viselkedés'); ?>
  <?php row('Habitus:', htmlspecialchars($raw['dogBehavior'] ?? '')); ?>
  <?php row('Megjegyzés:', nl2br(htmlspecialchars($raw['dogComment'] ?? ''))); ?>

  <?php foreach (($raw['extraDogs'] ?? []) as $n => $d): ?>
    <?php sec(($d['name'] ?: 'További kutya') . " adatai ({$n}. kutya)"); ?>
    <?php row('Kutya neve:', htmlspecialchars($d['name'] ?? '')); ?>
    <?php row('Fajta:', htmlspecialchars($d['breed'] ?? '')); ?>
    <?php row('Kor:', htmlspecialchars($d['age'] ?? '')); ?>
    <?php row('Nem:', htmlspecialchars($d['gender'] ?? '')); ?>
    <?php row('Ivartalanított:', htmlspecialchars($d['neutered'] ?? '')); ?>
    <?php row('Chip száma:', htmlspecialchars($d['chip'] ?? '')); ?>
    <?php row('Mióta van a kutya:', htmlspecialchars($d['since'] ?? '')); ?>
    <?php row('Etetés típusa:', htmlspecialchars($d['food'] ?? '')); ?>
    <?php row('Napi adag:', !empty($d['foodAmt']) ? htmlspecialchars($d['foodAmt']) . ' g' : ''); ?>
    <?php row('Veszettség oltás:', !empty($d['rabiesFlag']) ? warn(($d['vaccRabies'] ?? '') . ' — 14 napon belüli oltás!') : htmlspecialchars($d['vaccRabies'] ?? '')); ?>
    <?php row('Kombinált oltás:', htmlspecialchars($d['vaccCombo'] ?? '')); ?>
    <?php row('Utolsó féreghajtó neve:', htmlspecialchars($d['wormerName'] ?? '')); ?>
    <?php row('Utolsó féreghajtó dátuma:', !empty($d['wormerFlag']) ? warn(($d['wormerDate'] ?? '') . ' — 3 hónapnál régebbi!') : htmlspecialchars($d['wormerDate'] ?? '')); ?>
    <?php row('Betegség:', htmlspecialchars($d['illness'] ?? '')); ?>
    <?php row('Gyógyszer:', htmlspecialchars($d['meds'] ?? '')); ?>
    <?php row('Trauma/Támadás:', htmlspecialchars($d['trauma'] ?? '')); ?>
    <?php row('Habitus:', htmlspecialchars($d['behavior'] ?? '')); ?>
  <?php endforeach; ?>

  <?php sec($booking['dogs_count'] > 1 ? "Foglalás részletei ({$booking['dogs_count']} kutya)" : 'Foglalás részletei'); ?>
  <?php row('Naponkénti csomagok:', nl2br(htmlspecialchars(str_replace('||', "\n", $booking['packages_raw'] ?? '')))); ?>
  <?php row('Érkezés:', htmlspecialchars($booking['date_from'])); ?>
  <?php row('Távozás:', htmlspecialchars($booking['date_to'])); ?>
  <?php row('Becsült végösszeg:', number_format((float)$booking['total'], 0, ',', ' ') . ' Ft'); ?>
  <?php row('Foglaló (50%):', number_format((float)$booking['deposit'], 0, ',', ' ') . ' Ft'); ?>
  <?php row('Maradék (távozáskor):', number_format((float)$booking['remainder'], 0, ',', ' ') . ' Ft'); ?>
  <?php row('Fizetés ütemezése:', htmlspecialchars($booking['payment_timing'])); ?>
  <?php row('Fizetési mód:', htmlspecialchars($booking['payment_method'])); ?>
</table>
</div>

<div class="dl-actions">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
    <input type="hidden" name="action" value="accept">
    <button type="submit" class="btn btn-success" <?= $booking['status'] === 'accepted' ? 'disabled' : '' ?>>Elfogadás</button>
  </form>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
    <input type="hidden" name="action" value="decline">
    <button type="submit" class="btn btn-danger" <?= $booking['status'] === 'declined' ? 'disabled' : '' ?>>Elutasítás</button>
  </form>
  <a href="mailto:<?= htmlspecialchars($booking['owner_email']) ?>" class="btn btn-outline">✉ Email írása</a>
  <form method="post" onsubmit="return confirm('Biztosan törlöd <?= htmlspecialchars(addslashes($booking['dog_name'] ?: 'ezt a foglalást')) ?> foglalását? Ez nem vonható vissza.');" style="margin-left:auto;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
    <input type="hidden" name="action" value="delete">
    <button type="submit" class="btn btn-delete">Törlés</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
