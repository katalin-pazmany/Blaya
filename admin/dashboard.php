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

$yearParam = (int)($_GET['year'] ?? $first->format('Y'));
$yearFirst = new DateTime($yearParam . '-01-01');
$yearLast = new DateTime($yearParam . '-12-31');
$prevYear = $yearParam - 1;
$nextYear = $yearParam + 1;

$monthNames = ['Január','Február','Március','Április','Május','Június','Július','Augusztus','Szeptember','Október','November','December'];
$monthNamesLower = ['január','február','március','április','május','június','július','augusztus','szeptember','október','november','december'];
$monthNamesShort = ['Jan','Feb','Már','Ápr','Máj','Jún','Júl','Aug','Szep','Okt','Nov','Dec'];

$month = getStatsForRange($first->format('Y-m-d'), $last->format('Y-m-d'));
$year = getStatsForRange($yearFirst->format('Y-m-d'), $yearLast->format('Y-m-d'));

$daysInMonth = (int)$last->format('j');
$daysInYear = (int)$yearLast->format('z') + 1;
$monthBookedDays = count(getAcceptedDogCounts($first->format('Y-m-d'), $last->format('Y-m-d')));
$yearBookedDays = count(getAcceptedDogCounts($yearFirst->format('Y-m-d'), $yearLast->format('Y-m-d')));

// Last 6 months of accepted vs. declined dogs, for the trend chart.
$trendMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $m = (clone $first)->modify("-{$i} months");
    $mLast = (clone $m)->modify('last day of this month');
    $s = getStatsForRange($m->format('Y-m-01'), $mLast->format('Y-m-d'));
    $trendMonths[] = [
        'label'    => $monthNamesShort[(int)$m->format('n') - 1],
        'accepted' => $s['accepted']['dogs'],
        'declined' => $s['declined']['dogs'],
    ];
}
$trendMax = max(array_merge(array_column($trendMonths, 'accepted'), array_column($trendMonths, 'declined')));
$trendMax = max(5, (int)ceil($trendMax / 5) * 5);

function fmtFt($amount) {
    return number_format((float)$amount, 0, ',', ' ') . ' Ft';
}

// One donut, three segments (accepted/pending/declined) — the ring itself
// shows the mix at a glance, the legend rows give the exact head counts.
function statusDonut(int $accepted, int $pending, int $declined): void {
    $total = $accepted + $pending + $declined;
    if ($total > 0) {
        $accEnd = $accepted / $total * 100;
        $penEnd = $accEnd + $pending / $total * 100;
        $gradient = sprintf(
            'conic-gradient(var(--success) 0%% %1$s%%, var(--primary) %1$s%% %2$s%%, var(--danger) %2$s%% 100%%)',
            $accEnd, $penEnd
        );
    } else {
        $gradient = 'conic-gradient(rgba(255,255,255,0.08) 0 100%)';
    }
    echo '<div class="ring" style="background:' . $gradient . ';">';
    echo '<div class="ring-inner"><div class="ring-value">' . $total . '</div><div class="ring-sub">kutya összesen</div></div>';
    echo '</div>';
}

function statusDonutCard(string $title, array $stats): void {
    echo '<div class="stat-tile card-highlight status-card span-4 span-4-lg">';
    echo '<div class="stat-label">' . htmlspecialchars($title) . '</div>';
    echo '<div class="status-card-body">';
    statusDonut($stats['accepted']['dogs'], $stats['pending']['dogs'], $stats['declined']['dogs']);
    echo '<div class="status-legend">';
    echo '<div class="legend-row"><span class="dot success"></span>Elfogadva<span class="legend-count">' . $stats['accepted']['dogs'] . '</span></div>';
    echo '<div class="legend-row"><span class="dot primary"></span>Függőben<span class="legend-count">' . $stats['pending']['dogs'] . '</span></div>';
    echo '<div class="legend-row"><span class="dot danger"></span>Elutasítva<span class="legend-count">' . $stats['declined']['dogs'] . '</span></div>';
    echo '</div></div></div>';
}

function ratioCard(string $title, ?int $pct): void {
    $val = $pct !== null ? $pct : 0;
    echo '<div class="stat-tile card-highlight span-4 span-4-lg">';
    echo '<div class="stat-label">' . htmlspecialchars($title) . '</div>';
    echo '<div class="ratio-value">' . ($pct !== null ? $pct . '%' : '–') . '</div>';
    echo '<div class="progress-track"><div class="progress-fill" style="width:' . max(0, min(100, $val)) . '%;"></div></div>';
    echo '</div>';
}

// x/y coordinates for a 6-point line, scaled into a fixed SVG viewBox.
function trendLinePoints(array $values, int $max, float $w, float $h, float $padL, float $padR, float $padT, float $padB): array {
    $n = count($values);
    $usableW = $w - $padL - $padR;
    $usableH = $h - $padT - $padB;
    $points = [];
    foreach ($values as $i => $v) {
        $x = $padL + ($n > 1 ? $i / ($n - 1) * $usableW : $usableW / 2);
        $y = $padT + $usableH - ($max > 0 ? $v / $max * $usableH : 0);
        $points[] = [round($x, 1), round($y, 1)];
    }
    return $points;
}
function trendPathD(array $points): string {
    $d = '';
    foreach ($points as $i => $p) {
        $d .= ($i === 0 ? 'M' : 'L') . $p[0] . ',' . $p[1] . ' ';
    }
    return trim($d);
}

$pageTitle = 'Áttekintés';
$activeNav = 'dashboard';
require __DIR__ . '/includes/layout_header.php';

$svgW = 640; $svgH = 190; $padL = 8; $padR = 8; $padT = 12; $padB = 12;
$accPoints = trendLinePoints(array_column($trendMonths, 'accepted'), $trendMax, $svgW, $svgH, $padL, $padR, $padT, $padB);
$decPoints = trendLinePoints(array_column($trendMonths, 'declined'), $trendMax, $svgW, $svgH, $padL, $padR, $padT, $padB);
?>
<style>
.dash-title-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 32px 0 18px; }
.dash-title-row a { font-family: 'Oswald', sans-serif; font-size: 12px; letter-spacing: 1px; text-decoration: none; color: var(--text-muted); border: 1px solid var(--hairline); border-radius: var(--radius-sm); padding: 7px 14px; white-space: nowrap; }
.dash-title-row a:hover { border-color: var(--primary); color: var(--primary); }
.dash-title { font-family: 'Oswald', sans-serif; font-size: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-primary); text-align: center; }
.dash-title .accent { color: var(--primary); }

.dash-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 14px; margin-bottom: 14px; }
.dash-grid > * { grid-column: span 12; }
@media (min-width: 640px) {
  .dash-grid > .span-6 { grid-column: span 6; }
}
@media (min-width: 900px) {
  .dash-grid > .span-4-lg { grid-column: span 4; }
}

.stat-tile { background: var(--card-bg); border: 1px solid var(--hairline); border-radius: var(--radius-lg); padding: 20px; box-shadow: var(--shadow-md); }
.stat-tile.card-highlight { border: 1.5px solid var(--primary); }
.stat-label { font-family: 'Oswald', sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 10px; }
.stat-value { font-family: 'Oswald', sans-serif; font-size: 28px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.1; }
.stat-tile.card-highlight .stat-value { color: var(--primary); }
.stat-sub { font-size: 12px; color: var(--text-muted); margin-top: 6px; }

.ratio-value { font-family: 'Oswald', sans-serif; font-size: 28px; font-weight: 700; color: var(--primary); font-variant-numeric: tabular-nums; }
.progress-track { height: 8px; background: rgba(255,255,255,0.08); border-radius: 999px; margin-top: 16px; overflow: hidden; }
.progress-fill { height: 100%; background: var(--primary); border-radius: 999px; }

.ring { width: 92px; height: 92px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ring-inner { width: 70px; height: 70px; border-radius: 50%; background: var(--card-bg); display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ring-value { font-family: 'Oswald', sans-serif; font-size: 18px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.ring-sub { font-size: 9px; color: var(--text-muted); margin-top: 1px; text-align: center; padding: 0 4px; line-height: 1.2; }

.status-card-body { display: flex; align-items: center; gap: 18px; }
.status-legend { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 0; }
.legend-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-secondary); }
.legend-row .dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.legend-row .dot.success { background: var(--success); }
.legend-row .dot.primary { background: var(--primary); }
.legend-row .dot.danger { background: var(--danger); }
.legend-count { margin-left: auto; font-family: 'Oswald', sans-serif; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--text-primary); font-size: 14px; }

.trend-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
.chart-toggle { display: flex; gap: 6px; }
.chart-toggle-btn { font-family: 'Oswald', sans-serif; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; padding: 7px 16px; border-radius: var(--radius-sm); border: 1px solid var(--hairline); background: transparent; color: var(--text-muted); cursor: pointer; }
.chart-toggle-btn.active { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }

.trend-svg { width: 100%; height: auto; display: block; }
.trend-grid-line { stroke: rgba(255,255,255,0.08); stroke-width: 1; }
.trend-grouped-bars { display: flex; align-items: flex-end; gap: 12px; height: 180px; padding: 0 4px; }
.grouped-bar-col { flex: 1; display: flex; align-items: flex-end; justify-content: center; gap: 4px; height: 100%; }
.gbar { width: 20px; border-radius: 4px 4px 0 0; min-height: 3px; }
.gbar.success { background: var(--success); }
.gbar.danger { background: var(--danger); }

.trend-numbers { display: flex; gap: 12px; margin-top: 14px; padding: 0 4px; }
.trend-num-col { flex: 1; text-align: center; }
.trend-num-month { font-size: 11px; color: var(--text-muted); font-family: 'Oswald', sans-serif; letter-spacing: .5px; margin-bottom: 4px; }
.trend-num { font-family: 'Oswald', sans-serif; font-weight: 700; font-size: 13px; font-variant-numeric: tabular-nums; }
.trend-num.success { color: var(--success); }
.trend-num.danger { color: var(--danger); }

.trend-legend-row { display: flex; gap: 20px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--hairline); }
.legend-chip { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); }
.legend-chip .dot { width: 8px; height: 8px; border-radius: 50%; }
.legend-chip .dot.success { background: var(--success); }
.legend-chip .dot.danger { background: var(--danger); }

.dash-footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--hairline); font-size: 12px; color: var(--text-muted); }
.dash-footer .accent { color: var(--primary); }

@media (max-width: 480px) {
  .status-card-body { flex-direction: column; }
}
</style>

<div class="page-head">
  <h1>Áttekintés</h1>
</div>

<div class="dash-title-row">
  <a href="dashboard.php?month=<?= $prevMonth ?>&year=<?= $yearParam ?>">&larr;</a>
  <div class="dash-title">Havi áttekintés <span class="accent">— <?= $monthNames[(int)$first->format('n') - 1] ?></span></div>
  <a href="dashboard.php?month=<?= $nextMonth ?>&year=<?= $yearParam ?>">&rarr;</a>
</div>

<div class="dash-grid">
  <div class="stat-tile card-highlight span-4 span-4-lg">
    <div class="stat-label">Havi bevétel</div>
    <div class="stat-value"><?= fmtFt($month['accepted']['total']) ?></div>
    <div class="stat-sub">bevétel ebben a hónapban</div>
  </div>
  <?php statusDonutCard('Kutyák — elfogadva / függőben / elutasítva', $month); ?>
  <?php ratioCard('Elfogadási arány', $month['acceptanceRate']); ?>
</div>
<div class="dash-grid">
  <div class="stat-tile span-6">
    <div class="stat-label">Foglalt napok e hónapban</div>
    <div class="stat-value"><?= $monthBookedDays ?></div>
    <div class="stat-sub"><?= $daysInMonth ?> napból (<?= $monthNamesLower[(int)$first->format('n') - 1] ?>)</div>
  </div>
  <div class="stat-tile span-6">
    <div class="stat-label">Összes foglalás</div>
    <div class="stat-value"><?= $month['requestsCount'] ?></div>
    <div class="stat-sub">beérkezett ebben a hónapban</div>
  </div>
</div>

<div class="dash-title-row">
  <div class="dash-title" style="text-align:left;">Kutyák trendje <span class="accent">— utolsó 6 hónap</span></div>
  <div class="chart-toggle">
    <button type="button" class="chart-toggle-btn" data-mode="bar">Oszlop</button>
    <button type="button" class="chart-toggle-btn active" data-mode="line">Vonal</button>
  </div>
</div>
<div class="stat-tile">
  <div class="chart-view" data-view="line">
    <svg class="trend-svg" viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" preserveAspectRatio="none">
      <?php for ($g = 0; $g <= 4; $g++): $gy = $padT + ($svgH - $padT - $padB) * $g / 4; ?>
        <line class="trend-grid-line" x1="<?= $padL ?>" y1="<?= round($gy, 1) ?>" x2="<?= $svgW - $padR ?>" y2="<?= round($gy, 1) ?>" />
      <?php endfor; ?>
      <path d="<?= trendPathD($decPoints) ?>" fill="none" stroke="var(--danger)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
      <path d="<?= trendPathD($accPoints) ?>" fill="none" stroke="var(--success)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
      <?php foreach ($decPoints as $p): ?><circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="4" fill="var(--danger)" /><?php endforeach; ?>
      <?php foreach ($accPoints as $p): ?><circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="4" fill="var(--success)" /><?php endforeach; ?>
    </svg>
  </div>
  <div class="chart-view" data-view="bar" style="display:none;">
    <div class="trend-grouped-bars">
      <?php foreach ($trendMonths as $t): ?>
        <div class="grouped-bar-col">
          <div class="gbar success" style="height: <?= max(3, round($t['accepted'] / $trendMax * 100)) ?>%;" title="Elfogadva: <?= $t['accepted'] ?>"></div>
          <div class="gbar danger" style="height: <?= max(3, round($t['declined'] / $trendMax * 100)) ?>%;" title="Elutasítva: <?= $t['declined'] ?>"></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="trend-numbers">
    <?php foreach ($trendMonths as $t): ?>
      <div class="trend-num-col">
        <div class="trend-num-month"><?= htmlspecialchars($t['label']) ?></div>
        <div class="trend-num success"><?= $t['accepted'] ?></div>
        <div class="trend-num danger"><?= $t['declined'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="trend-legend-row">
    <span class="legend-chip"><span class="dot success"></span>Elfogadva</span>
    <span class="legend-chip"><span class="dot danger"></span>Elutasítva</span>
  </div>
</div>

<div class="dash-title-row">
  <a href="dashboard.php?year=<?= $prevYear ?>&month=<?= $monthParam ?>">&larr;</a>
  <div class="dash-title">Éves áttekintés <span class="accent">— <?= $yearParam ?></span></div>
  <a href="dashboard.php?year=<?= $nextYear ?>&month=<?= $monthParam ?>">&rarr;</a>
</div>

<div class="dash-grid">
  <div class="stat-tile card-highlight span-4 span-4-lg">
    <div class="stat-label">Éves bevétel</div>
    <div class="stat-value"><?= fmtFt($year['accepted']['total']) ?></div>
    <div class="stat-sub">bevétel <?= $yearParam ?>-ban</div>
  </div>
  <?php statusDonutCard('Kutyák — elfogadva / függőben / elutasítva', $year); ?>
  <?php ratioCard('Elfogadási arány', $year['acceptanceRate']); ?>
</div>
<div class="dash-grid">
  <div class="stat-tile span-6">
    <div class="stat-label">Foglalt napok <?= $yearParam ?>-ban</div>
    <div class="stat-value"><?= $yearBookedDays ?></div>
    <div class="stat-sub"><?= $daysInYear ?> napból</div>
  </div>
  <div class="stat-tile span-6">
    <div class="stat-label">Összes foglalás</div>
    <div class="stat-value"><?= $year['requestsCount'] ?></div>
    <div class="stat-sub">beérkezett <?= $yearParam ?>-ban</div>
  </div>
</div>

<div class="dash-footer">
  BLAYA Kutyapanzió · Adatok dátuma: <span class="accent"><?= date('Y') . '. ' . $monthNamesLower[(int)date('n') - 1] . ' ' . date('j') . '.' ?></span>
</div>

<script>
document.querySelectorAll('.chart-toggle-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var mode = btn.dataset.mode;
    document.querySelectorAll('.chart-toggle-btn').forEach(function (b) { b.classList.toggle('active', b === btn); });
    document.querySelectorAll('.chart-view').forEach(function (v) { v.style.display = v.dataset.view === mode ? '' : 'none'; });
  });
});
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
