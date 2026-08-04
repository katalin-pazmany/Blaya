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

$monthNames = ['Január','Február','Március','Április','Május','Június','Július','Augusztus','Szeptember','Október','November','December'];
$monthNamesShort = ['Jan','Feb','Már','Ápr','Máj','Jún','Júl','Aug','Szep','Okt','Nov','Dec'];

$month = getStatsForRange($first->format('Y-m-d'), $last->format('Y-m-d'));
$allTime = getStatsForRange(null, null);

// Share of this month's requested dogs in each status — drives the ring fills.
$monthDogsTotal = $month['accepted']['dogs'] + $month['declined']['dogs'] + $month['pending']['dogs'];
$acceptedPct = $monthDogsTotal > 0 ? $month['accepted']['dogs'] / $monthDogsTotal * 100 : 0;
$declinedPct = $monthDogsTotal > 0 ? $month['declined']['dogs'] / $monthDogsTotal * 100 : 0;
$pendingPct  = $monthDogsTotal > 0 ? $month['pending']['dogs']  / $monthDogsTotal * 100 : 0;

// Last 6 months of income (accepted bookings), for the trend sparkline.
$incomeTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $m = (clone $first)->modify("-{$i} months");
    $mLast = (clone $m)->modify('last day of this month');
    $s = getStatsForRange($m->format('Y-m-01'), $mLast->format('Y-m-d'));
    $incomeTrend[] = [
        'label'     => $monthNamesShort[(int)$m->format('n') - 1],
        'income'    => $s['accepted']['total'],
        'isCurrent' => $m->format('Y-m') === $first->format('Y-m'),
    ];
}
$maxTrendIncome = max(1, max(array_column($incomeTrend, 'income')));

function fmtFt($amount) {
    return number_format((float)$amount, 0, ',', ' ') . ' Ft';
}

function ring(string $bigValue, string $subLabel, float $pct, string $colorVar): void {
    $pct = max(0, min(100, $pct));
    echo '<div class="ring" style="--pct:' . $pct . ';--ring-color:' . htmlspecialchars($colorVar) . ';">';
    echo '<div class="ring-inner"><div class="ring-value">' . $bigValue . '</div><div class="ring-sub">' . htmlspecialchars($subLabel) . '</div></div>';
    echo '</div>';
}

$pageTitle = 'Áttekintés';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout_header.php';
?>
<style>
.dash-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.dash-nav a { font-family: 'Oswald', sans-serif; font-size: 13px; letter-spacing: 1px; text-decoration: none; color: var(--text-primary); border: 1.5px solid rgba(255,255,255,0.2); border-radius: var(--radius-sm); padding: 8px 16px; }
.dash-nav a:hover { border-color: var(--primary); color: var(--primary); }
.dash-nav h2 { font-size: 20px; text-transform: uppercase; letter-spacing: 1px; }

.dash-group-header { background: var(--card-bg); border: 1px solid var(--hairline); border-radius: var(--radius-md); padding: 13px 20px; font-family: 'Oswald', sans-serif; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-primary); margin: 28px 0 14px; }
.dash-group-header:first-of-type { margin-top: 0; }
.dash-group-header .muted { color: var(--text-muted); font-weight: 400; text-transform: none; letter-spacing: 0; margin-left: 8px; font-family: 'Lato', sans-serif; }

.dash-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 14px; }
.dash-grid > * { grid-column: span 12; }
@media (min-width: 640px) {
  .dash-grid > .span-6 { grid-column: span 6; }
  .dash-grid > .span-4 { grid-column: span 4; }
  .dash-grid > .span-3 { grid-column: span 3; }
}
@media (min-width: 900px) {
  .dash-grid > .span-6-lg { grid-column: span 6; }
  .dash-grid > .span-3-lg { grid-column: span 3; }
  .dash-grid > .span-2-lg { grid-column: span 2; }
}

.stat-tile { background: var(--card-bg); border: 1px solid var(--hairline); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-md); }
.stat-label { font-family: 'Oswald', sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 10px; }
.stat-value { font-family: 'Oswald', sans-serif; font-size: 28px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.1; }
.stat-sub { font-size: 12px; color: var(--text-muted); margin-top: 6px; }

.trend-card .stat-value { font-size: 34px; }
.trend-bars { display: flex; align-items: flex-end; gap: 10px; height: 64px; margin-top: 18px; }
.trend-bar { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 6px; }
.trend-bar .bar { width: 100%; max-width: 28px; background: rgba(255,255,255,0.12); border-radius: 4px 4px 0 0; min-height: 3px; }
.trend-bar.current .bar { background: var(--primary); }
.trend-bar .bar-label { font-size: 10px; color: var(--text-muted); font-family: 'Oswald', sans-serif; letter-spacing: .5px; }

.ring-card { background: var(--card-bg); border: 1px solid var(--hairline); border-radius: var(--radius-lg); padding: 18px; box-shadow: var(--shadow-md); display: flex; flex-direction: column; align-items: center; gap: 12px; }
.ring-title { font-family: 'Oswald', sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); align-self: flex-start; }
.ring { --pct: 0; --ring-color: var(--primary); width: 108px; height: 108px; border-radius: 50%; background: conic-gradient(var(--ring-color) calc(var(--pct) * 1%), rgba(255,255,255,0.08) 0); display: flex; align-items: center; justify-content: center; }
.ring-inner { width: 84px; height: 84px; border-radius: 50%; background: var(--card-bg); display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ring-value { font-family: 'Oswald', sans-serif; font-size: 22px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.ring-sub { font-size: 11px; color: var(--text-muted); margin-top: 2px; text-align: center; padding: 0 6px; }
</style>

<div class="page-head">
  <h1>Áttekintés</h1>
</div>

<div class="dash-nav">
  <a href="dashboard.php?month=<?= $prevMonth ?>">&larr; Előző hónap</a>
  <h2><?= $monthNames[(int)$first->format('n') - 1] ?> <?= $first->format('Y') ?></h2>
  <a href="dashboard.php?month=<?= $nextMonth ?>">Következő hónap &rarr;</a>
</div>

<div class="dash-group-header"><?= $monthNames[(int)$first->format('n') - 1] ?> <?= $first->format('Y') ?><span class="muted">— ez a hónap</span></div>
<div class="dash-grid">
  <div class="stat-tile trend-card span-6-lg">
    <div class="stat-label">Bevétel</div>
    <div class="stat-value"><?= fmtFt($month['accepted']['total']) ?></div>
    <div class="stat-sub">elfogadott foglalások alapján</div>
    <div class="trend-bars">
      <?php foreach ($incomeTrend as $t): ?>
        <div class="trend-bar<?= $t['isCurrent'] ? ' current' : '' ?>">
          <div class="bar" style="height: <?= max(4, round($t['income'] / $maxTrendIncome * 100)) ?>%;" title="<?= htmlspecialchars($t['label'] . ': ' . fmtFt($t['income'])) ?>"></div>
          <div class="bar-label"><?= htmlspecialchars($t['label']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="ring-card span-4 span-2-lg">
    <div class="ring-title">Elfogadva</div>
    <?php ring((string)$month['accepted']['dogs'], 'kutya', $acceptedPct, 'var(--success)'); ?>
  </div>
  <div class="ring-card span-4 span-2-lg">
    <div class="ring-title">Függőben</div>
    <?php ring((string)$month['pending']['dogs'], 'kutya elbírálásra vár', $pendingPct, 'var(--primary)'); ?>
  </div>
  <div class="ring-card span-4 span-2-lg">
    <div class="ring-title">Elutasítva</div>
    <?php ring((string)$month['declined']['dogs'], 'kutya', $declinedPct, 'var(--danger)'); ?>
  </div>

  <div class="stat-tile">
    <div class="stat-label">Beérkezett kérelmek</div>
    <div class="stat-value"><?= $month['requestsCount'] ?></div>
    <div class="stat-sub">érkezés dátuma e hónapban</div>
  </div>
</div>

<div class="dash-group-header">Összesen<span class="muted">— minden idő</span></div>
<div class="dash-grid">
  <div class="stat-tile span-6 span-3-lg">
    <div class="stat-label">Befogadott kutyák</div>
    <div class="stat-value"><?= $allTime['accepted']['dogs'] ?></div>
    <div class="stat-sub"><?= $allTime['accepted']['bookings'] ?> foglalásból</div>
  </div>
  <div class="stat-tile span-6 span-3-lg">
    <div class="stat-label">Bevétel</div>
    <div class="stat-value"><?= fmtFt($allTime['accepted']['total']) ?></div>
    <div class="stat-sub">elfogadott foglalások alapján</div>
  </div>
  <div class="stat-tile span-6 span-3-lg">
    <div class="stat-label">Elutasított kutyák</div>
    <div class="stat-value"><?= $allTime['declined']['dogs'] ?></div>
    <div class="stat-sub"><?= $allTime['declined']['bookings'] ?> foglalásból</div>
  </div>
  <div class="stat-tile span-6 span-3-lg">
    <div class="stat-label">Összes kérelem</div>
    <div class="stat-value"><?= $allTime['requestsCount'] ?></div>
    <div class="stat-sub">az induló óta</div>
  </div>
  <div class="ring-card span-6 span-3-lg">
    <div class="ring-title">Elfogadási arány</div>
    <?php ring($allTime['acceptanceRate'] !== null ? $allTime['acceptanceRate'] . '%' : '–', 'elfogadott / elbírált', $allTime['acceptanceRate'] ?? 0, 'var(--primary)'); ?>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
