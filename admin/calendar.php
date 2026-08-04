<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';

$monthParam = $_GET['month'] ?? date('Y-m');
$first = DateTime::createFromFormat('Y-m-d', $monthParam . '-01');
if (!$first) {
    $first = new DateTime(date('Y-m-01'));
}
$last = (clone $first)->modify('last day of this month');

$prevMonth = (clone $first)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $first)->modify('+1 month')->format('Y-m');
$todayStr = date('Y-m-d');

$monthNames = ['Január','Február','Március','Április','Május','Június','Július','Augusztus','Szeptember','Október','November','December'];
$dayNames = ['H','K','Sze','Cs','P','Szo','V'];

$rangeStart = $first->format('Y-m-d');
$rangeEnd = $last->format('Y-m-d');
$bookings = getAcceptedBookingsInRange($rangeStart, $rangeEnd);

// Expand each booking into the individual days (within this month) it occupies.
$byDay = [];
foreach ($bookings as $b) {
    $from = DateTime::createFromFormat('Y-m-d', $b['date_from']);
    if (!$from) continue;
    $to = DateTime::createFromFormat('Y-m-d', $b['date_to']);
    if (!$to || $to < $from) $to = clone $from;

    $cursor = max($from, $first);
    $stop = min($to, $last);
    while ($cursor <= $stop) {
        $key = $cursor->format('Y-m-d');
        $byDay[$key][] = $b;
        $cursor->modify('+1 day');
    }
}

$startOffset = ((int)$first->format('N')) - 1; // Monday = 0
$daysInMonth = (int)$last->format('d');

$pageTitle = 'Naptár';
$activeNav = 'calendar';
require __DIR__ . '/includes/layout_header.php';
?>
<style>
.cal-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.cal-nav a { font-family: 'Oswald', sans-serif; font-size: 13px; letter-spacing: 1px; text-decoration: none; color: var(--text-primary); border: 1.5px solid rgba(255,255,255,0.2); border-radius: var(--radius-sm); padding: 8px 16px; }
.cal-nav a:hover { border-color: var(--primary); color: var(--primary); }
.cal-nav h2 { font-size: 20px; text-transform: uppercase; letter-spacing: 1px; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--hairline); border: 1px solid var(--hairline); border-radius: var(--radius-lg); overflow: hidden; }
.cal-dow { background: rgba(255,255,255,0.03); padding: 8px; text-align: center; font-family: 'Oswald', sans-serif; font-size: 11px; letter-spacing: 1px; color: var(--text-muted); text-transform: uppercase; }
.cal-cell { background: var(--card-bg); min-height: 96px; padding: 8px; display: flex; flex-direction: column; gap: 4px; }
.cal-cell.empty { background: rgba(0,0,0,0.15); }
.cal-cell.today .cal-daynum { color: var(--on-primary); background: var(--primary); border-radius: 999px; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; }
.cal-daynum { font-family: 'Oswald', sans-serif; font-size: 13px; color: var(--text-muted); }
.cal-pill { display: block; background: rgba(46,125,50,0.18); border: 1px solid rgba(111,207,115,0.4); color: #b9ecba; font-size: 11px; padding: 2px 6px; border-radius: 4px; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cal-pill:hover { background: rgba(46,125,50,0.32); }
.cal-legend { margin-top: 16px; font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
.cal-legend .cal-pill { width: 16px; height: 12px; padding: 0; display: inline-block; }
@media (max-width: 720px) {
  .cal-grid { grid-template-columns: repeat(7, minmax(0,1fr)); font-size: 11px; }
  .cal-cell { min-height: 64px; padding: 4px; }
}
</style>

<div class="page-head">
  <h1>Nap<span>tár</span></h1>
</div>

<div class="cal-nav">
  <a href="calendar.php?month=<?= $prevMonth ?>">&larr; Előző</a>
  <h2><?= $monthNames[(int)$first->format('n') - 1] ?> <?= $first->format('Y') ?></h2>
  <a href="calendar.php?month=<?= $nextMonth ?>">Következő &rarr;</a>
</div>

<div class="cal-grid">
  <?php foreach ($dayNames as $dn): ?>
    <div class="cal-dow"><?= $dn ?></div>
  <?php endforeach; ?>

  <?php for ($i = 0; $i < $startOffset; $i++): ?>
    <div class="cal-cell empty"></div>
  <?php endfor; ?>

  <?php for ($d = 1; $d <= $daysInMonth; $d++):
    $dateKey = $first->format('Y-m') . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    $isToday = $dateKey === $todayStr;
    $dayBookings = $byDay[$dateKey] ?? [];
  ?>
    <div class="cal-cell<?= $isToday ? ' today' : '' ?>">
      <span class="cal-daynum"><?= $d ?></span>
      <?php foreach ($dayBookings as $b): ?>
        <a class="cal-pill" href="booking.php?id=<?= (int)$b['id'] ?>" title="<?= htmlspecialchars($b['dog_name'] . ' — ' . $b['owner_name']) ?>">
          🐾 <?= htmlspecialchars($b['dog_name'] ?: 'Foglalás') ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>

<div class="cal-legend"><span class="cal-pill"></span> Elfogadott foglalás</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
