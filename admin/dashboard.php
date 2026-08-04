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

$month = getStatsForRange($first->format('Y-m-d'), $last->format('Y-m-d'));
$allTime = getStatsForRange(null, null);

function fmtFt($amount) {
    return number_format((float)$amount, 0, ',', ' ') . ' Ft';
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

.dash-section-title { font-family: 'Oswald', sans-serif; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: var(--text-muted); margin: 32px 0 14px; }
.dash-section-title:first-of-type { margin-top: 0; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
.stat-tile { background: var(--card-bg); border: 1px solid var(--hairline); border-top: 3px solid var(--hairline); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-md); }
.stat-tile.accent-success { border-top-color: var(--success); }
.stat-tile.accent-danger { border-top-color: var(--danger); }
.stat-tile.accent-primary { border-top-color: var(--primary); }
.stat-label { font-family: 'Oswald', sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 10px; }
.stat-value { font-family: 'Oswald', sans-serif; font-size: 28px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.1; }
.stat-sub { font-size: 12px; color: var(--text-muted); margin-top: 6px; }

@media (max-width: 480px) {
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="page-head">
  <h1>Áttekintés</h1>
</div>

<div class="dash-nav">
  <a href="dashboard.php?month=<?= $prevMonth ?>">&larr; Előző hónap</a>
  <h2><?= $monthNames[(int)$first->format('n') - 1] ?> <?= $first->format('Y') ?></h2>
  <a href="dashboard.php?month=<?= $nextMonth ?>">Következő hónap &rarr;</a>
</div>

<div class="stat-grid">
  <div class="stat-tile accent-success">
    <div class="stat-label">Befogadott kutyák</div>
    <div class="stat-value"><?= $month['accepted']['dogs'] ?></div>
    <div class="stat-sub"><?= $month['accepted']['bookings'] ?> foglalásból</div>
  </div>
  <div class="stat-tile accent-success">
    <div class="stat-label">Bevétel</div>
    <div class="stat-value"><?= fmtFt($month['accepted']['total']) ?></div>
    <div class="stat-sub">elfogadott foglalások alapján</div>
  </div>
  <div class="stat-tile accent-danger">
    <div class="stat-label">Elutasított kutyák</div>
    <div class="stat-value"><?= $month['declined']['dogs'] ?></div>
    <div class="stat-sub"><?= $month['declined']['bookings'] ?> foglalásból</div>
  </div>
  <div class="stat-tile accent-primary">
    <div class="stat-label">Függőben lévő kutyák</div>
    <div class="stat-value"><?= $month['pending']['dogs'] ?></div>
    <div class="stat-sub"><?= $month['pending']['bookings'] ?> foglalásból</div>
  </div>
  <div class="stat-tile">
    <div class="stat-label">Beérkezett kérelmek</div>
    <div class="stat-value"><?= $month['requestsCount'] ?></div>
    <div class="stat-sub">érkezés dátuma e hónapban</div>
  </div>
</div>

<div class="dash-section-title">Összesen (minden idő)</div>
<div class="stat-grid">
  <div class="stat-tile accent-success">
    <div class="stat-label">Befogadott kutyák</div>
    <div class="stat-value"><?= $allTime['accepted']['dogs'] ?></div>
    <div class="stat-sub"><?= $allTime['accepted']['bookings'] ?> foglalásból</div>
  </div>
  <div class="stat-tile accent-success">
    <div class="stat-label">Bevétel</div>
    <div class="stat-value"><?= fmtFt($allTime['accepted']['total']) ?></div>
    <div class="stat-sub">elfogadott foglalások alapján</div>
  </div>
  <div class="stat-tile accent-danger">
    <div class="stat-label">Elutasított kutyák</div>
    <div class="stat-value"><?= $allTime['declined']['dogs'] ?></div>
    <div class="stat-sub"><?= $allTime['declined']['bookings'] ?> foglalásból</div>
  </div>
  <div class="stat-tile">
    <div class="stat-label">Összes kérelem</div>
    <div class="stat-value"><?= $allTime['requestsCount'] ?></div>
    <div class="stat-sub">az induló óta</div>
  </div>
  <div class="stat-tile">
    <div class="stat-label">Elfogadási arány</div>
    <div class="stat-value"><?= $allTime['acceptanceRate'] !== null ? $allTime['acceptanceRate'] . '%' : '–' ?></div>
    <div class="stat-sub">elfogadott / elbírált kérelmek</div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
