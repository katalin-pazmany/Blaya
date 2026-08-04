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
$showPending = !empty($_GET['pending']);

$monthNames = ['Január','Február','Március','Április','Május','Június','Július','Augusztus','Szeptember','Október','November','December'];
$dayNames = ['H','K','Sze','Cs','P','Szo','V'];

// Full displayed grid: back up to Monday, forward to Sunday, so every week
// row is complete even at month boundaries.
$gridStart = (clone $first)->modify('-' . (((int)$first->format('N')) - 1) . ' days');
$gridEnd = (clone $last)->modify('+' . (7 - (int)$last->format('N')) . ' days');
$gridStartStr = $gridStart->format('Y-m-d');
$gridEndStr = $gridEnd->format('Y-m-d');

// The calendar always shows accepted stays; pending requests are an
// optional overlay (dashed/amber) so staff can spot a potential clash
// before deciding, without confusing them with confirmed bookings.
$bookings = getBookingsInRangeByStatus('accepted', $gridStartStr, $gridEndStr);
if ($showPending) {
    $bookings = array_merge($bookings, getBookingsInRangeByStatus('pending', $gridStartStr, $gridEndStr));
}
// Capacity counting only ever considers confirmed (accepted) stays.
$dayCounts = getAcceptedDogCounts($gridStartStr, $gridEndStr);

// Pre-parse each booking's own [from, to] once.
$parsed = [];
foreach ($bookings as $b) {
    $from = DateTime::createFromFormat('Y-m-d', $b['date_from']);
    if (!$from) continue;
    $to = DateTime::createFromFormat('Y-m-d', $b['date_to']);
    if (!$to || $to < $from) $to = clone $from;
    $parsed[] = ['booking' => $b, 'from' => $from, 'to' => $to];
}

// Build one week at a time: which bookings intersect it, spanning the
// correct day-of-week columns, packed into non-overlapping lanes so
// concurrent stays render as stacked rows instead of colliding.
$weeks = [];
$cursor = clone $gridStart;
while ($cursor <= $gridEnd) {
    $weekStart = clone $cursor;
    $weekEnd = (clone $cursor)->modify('+6 days');
    $days = [];
    for ($i = 0; $i < 7; $i++) $days[] = (clone $weekStart)->modify("+{$i} days");

    $events = [];
    foreach ($parsed as $p) {
        if ($p['to'] < $weekStart || $p['from'] > $weekEnd) continue;
        $clipStart = max($p['from'], $weekStart);
        $clipEnd = min($p['to'], $weekEnd);
        $events[] = [
            'id'        => $p['booking']['id'],
            'label'     => $p['booking']['dog_name'] ?: 'Foglalás',
            'owner'     => $p['booking']['owner_name'],
            'dogs'      => (int)$p['booking']['dogs_count'],
            'status'    => $p['booking']['status'],
            'startCol'  => (int)$clipStart->format('N'),
            'endCol'    => (int)$clipEnd->format('N'),
            'contStart' => $p['from'] < $weekStart,
            'contEnd'   => $p['to'] > $weekEnd,
        ];
    }
    usort($events, function ($a, $b) {
        if ($a['startCol'] !== $b['startCol']) return $a['startCol'] <=> $b['startCol'];
        return ($b['endCol'] - $b['startCol']) <=> ($a['endCol'] - $a['startCol']);
    });

    $laneEnds = [];
    foreach ($events as &$ev) {
        $lane = null;
        foreach ($laneEnds as $laneIdx => $end) {
            if ($end < $ev['startCol']) { $lane = $laneIdx; break; }
        }
        if ($lane === null) $lane = count($laneEnds);
        $ev['lane'] = $lane;
        $laneEnds[$lane] = $ev['endCol'];
    }
    unset($ev);

    $weeks[] = ['days' => $days, 'events' => $events, 'maxLanes' => max(1, count($laneEnds))];
    $cursor->modify('+7 days');
}

// Every week row uses the *month's* tallest lane count, not its own — so
// all week rows are the same height instead of a jagged, variable grid.
$globalMaxLanes = 1;
foreach ($weeks as $w) {
    $globalMaxLanes = max($globalMaxLanes, $w['maxLanes']);
}

$pageTitle = 'Naptár';
$activeNav = 'calendar';
require __DIR__ . '/includes/layout_header.php';
?>
<style>
.cal-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.cal-nav a { font-family: 'Oswald', sans-serif; font-size: 13px; letter-spacing: 1px; text-decoration: none; color: var(--text-primary); border: 1.5px solid rgba(255,255,255,0.2); border-radius: var(--radius-sm); padding: 8px 16px; }
.cal-nav a:hover { border-color: var(--primary); color: var(--primary); }
.cal-nav h2 { font-size: 20px; text-transform: uppercase; letter-spacing: 1px; }

.cal-toggle-row { margin-bottom: 20px; }
.cal-toggle { display: inline-flex; align-items: center; gap: 8px; font-family: 'Oswald', sans-serif; font-size: 12px; letter-spacing: 1px; text-transform: uppercase; text-decoration: none; color: var(--text-muted); border: 1px solid var(--hairline); border-radius: 999px; padding: 8px 16px; }
.cal-toggle:hover { border-color: var(--primary); color: var(--primary); }
.cal-toggle.active { background: rgba(249,153,5,0.15); border-color: rgba(249,153,5,0.4); color: var(--primary); }
.cal-toggle .dot { width: 8px; height: 8px; border-radius: 999px; background: currentColor; }

.cal-dow-row { display: grid; grid-template-columns: repeat(7, 1fr); margin-bottom: 4px; }
.cal-dow { text-align: center; font-family: 'Oswald', sans-serif; font-size: 11px; letter-spacing: 1px; color: var(--text-muted); text-transform: uppercase; padding: 4px 0; }

.cal-body { border: 1px solid var(--hairline); border-radius: var(--radius-lg); overflow: hidden; background: var(--card-bg); }
.cal-week { display: grid; grid-template-columns: repeat(7, 1fr); border-top: 1px solid var(--hairline); }
.cal-week:first-child { border-top: none; }
.cal-daycell { grid-row: 1; padding: 10px 10px 4px; border-right: 1px solid var(--hairline); display: flex; align-items: flex-start; justify-content: space-between; min-height: 40px; }
.cal-daycell:last-child { border-right: none; }
.cal-daycell.out-month { background: rgba(0,0,0,0.18); }
.cal-daycell.out-month .cal-daynum { color: rgba(255,255,255,0.25); }
.cal-daycell.full { background: rgba(222,14,14,0.08); }
.cal-daynum { font-family: 'Oswald', sans-serif; font-size: 13px; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.cal-daycell.today .cal-daynum { color: var(--on-primary); background: var(--primary); border-radius: 999px; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; }
.cal-full-dot { width: 8px; height: 8px; border-radius: 999px; background: var(--danger); margin-top: 4px; flex-shrink: 0; }

.cal-bar { margin: 1px 2px; padding: 3px 8px; background: rgba(46,125,50,0.28); border: 1px solid rgba(111,207,115,0.5); color: #d8f5d9; font-size: 11px; font-weight: 600; border-radius: 4px; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; }
.cal-bar:hover { background: rgba(46,125,50,0.45); }
.cal-bar.cont-start { border-top-left-radius: 0; border-bottom-left-radius: 0; border-left-style: dashed; margin-left: 0; }
.cal-bar.cont-end { border-top-right-radius: 0; border-bottom-right-radius: 0; border-right-style: dashed; margin-right: 0; }
.cal-bar.pending { background: rgba(249,153,5,0.16); border: 1px dashed rgba(249,153,5,0.6); color: #ffd9a0; }
.cal-bar.pending:hover { background: rgba(249,153,5,0.28); }

.cal-legend { margin-top: 16px; font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
.cal-legend .legend-item { display: flex; align-items: center; gap: 8px; }
.cal-legend .cal-bar { width: 18px; height: 12px; padding: 0; display: inline-block; margin: 0; }

@media (max-width: 720px) {
  .cal-daycell { padding: 4px; min-height: 30px; }
  .cal-bar { font-size: 9px; padding: 2px 4px; }
}
</style>

<div class="page-head">
  <h1>Naptár</h1>
</div>

<div class="cal-nav">
  <a href="calendar.php?month=<?= $prevMonth ?><?= $showPending ? '&pending=1' : '' ?>">&larr; Előző</a>
  <h2><?= $monthNames[(int)$first->format('n') - 1] ?> <?= $first->format('Y') ?></h2>
  <a href="calendar.php?month=<?= $nextMonth ?><?= $showPending ? '&pending=1' : '' ?>">Következő &rarr;</a>
</div>

<div class="cal-toggle-row">
  <a class="cal-toggle<?= $showPending ? ' active' : '' ?>" href="calendar.php?month=<?= $first->format('Y-m') ?><?= $showPending ? '' : '&pending=1' ?>">
    <span class="dot"></span>
    <?= $showPending ? 'Függőben lévők elrejtése' : 'Függőben lévő foglalások mutatása' ?>
  </a>
</div>

<div class="cal-dow-row">
  <?php foreach ($dayNames as $dn): ?>
    <div class="cal-dow"><?= $dn ?></div>
  <?php endforeach; ?>
</div>

<div class="cal-body">
  <?php foreach ($weeks as $week): ?>
    <div class="cal-week" style="grid-template-rows: 40px repeat(<?= $globalMaxLanes ?>, 30px);">
      <?php foreach ($week['days'] as $i => $day):
        $dateKey = $day->format('Y-m-d');
        $inMonth = $day->format('Y-m') === $first->format('Y-m');
        $count = $dayCounts[$dateKey] ?? 0;
        $isFull = $count >= MAX_DAILY_DOGS;
        $isToday = $dateKey === $todayStr;
      ?>
        <div class="cal-daycell<?= $inMonth ? '' : ' out-month' ?><?= $isFull ? ' full' : '' ?><?= $isToday ? ' today' : '' ?>"
             style="grid-column: <?= $i + 1 ?>;"
             title="<?= $isFull ? htmlspecialchars("Betelt: {$count}/" . MAX_DAILY_DOGS . " kutya") : '' ?>">
          <span class="cal-daynum"><?= (int)$day->format('j') ?></span>
          <?php if ($isFull): ?><span class="cal-full-dot"></span><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php foreach ($week['events'] as $ev): ?>
        <a class="cal-bar<?= $ev['status'] === 'pending' ? ' pending' : '' ?><?= $ev['contStart'] ? ' cont-start' : '' ?><?= $ev['contEnd'] ? ' cont-end' : '' ?>"
           style="grid-column: <?= $ev['startCol'] ?> / <?= $ev['endCol'] + 1 ?>; grid-row: <?= $ev['lane'] + 2 ?>;"
           href="booking.php?id=<?= (int)$ev['id'] ?>"
           title="<?= htmlspecialchars($ev['label'] . ' — ' . $ev['owner'] . ($ev['dogs'] > 1 ? ' (' . $ev['dogs'] . ' kutya)' : '') . ($ev['status'] === 'pending' ? ' [Függőben]' : '')) ?>">
          <?= $ev['status'] === 'pending' ? '⏳' : '🐾' ?> <?= htmlspecialchars($ev['label']) ?><?= $ev['dogs'] > 1 ? ' ×' . $ev['dogs'] : '' ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="cal-legend">
  <div class="legend-item"><span class="cal-bar"></span> Elfogadott foglalás</div>
  <?php if ($showPending): ?>
  <div class="legend-item"><span class="cal-bar pending"></span> Függőben lévő foglalás</div>
  <?php endif; ?>
  <div class="legend-item"><span class="cal-full-dot"></span> Betelt nap (<?= MAX_DAILY_DOGS ?> kutya)</div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
