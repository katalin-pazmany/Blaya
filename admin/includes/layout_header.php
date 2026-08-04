<?php
// Shared chrome for every admin page. Include after setting:
//   $pageTitle (string) and $activeNav ('bookings'|'calendar')
// require_login() must already have been called by the including page.
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · BLAYA Admin</title>
<link rel="icon" type="image/png" href="../paw-icon.svg">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Lato:wght@300;400;700&family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
<style>
:root {
  --primary: #F99905;
  --primary-hover: #e88a00;
  --danger: #DE0E0E;
  --success: #2e7d32;
  --dark: #1a1a1a;
  --card-bg: #222222;
  --white: #FFFFFF;
  --text-primary: #FFFFFF;
  --text-secondary: rgba(255,255,255,0.8);
  --text-muted: rgba(255,255,255,0.6);
  --on-primary: #1a1a1a;
  --border: rgba(249,153,5,0.25);
  --hairline: rgba(255,255,255,0.08);
  --shadow-md: 0 1px 2px rgba(0,0,0,0.28), 0 6px 16px -4px rgba(0,0,0,0.40);
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 16px;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Lato', sans-serif; background: var(--dark); color: var(--text-secondary); font-size: 16px; line-height: 1.6; min-height: 100vh; }
a { color: inherit; }
h1, h2, h3 { font-family: 'Oswald', sans-serif; color: var(--text-primary); }

nav { position: sticky; top: 0; z-index: 100; background: rgba(20,20,20,0.97); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); padding: 0 24px; display: flex; align-items: center; justify-content: space-between; height: 68px; flex-wrap: wrap; gap: 12px; }
.nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.nav-logo img { width: 44px; height: 44px; object-fit: contain; }
.nav-logo .logo-text { font-family: 'Oswald', sans-serif; font-size: 20px; font-weight: 700; color: var(--white); }
.nav-logo .logo-sub { font-family: 'Dancing Script', cursive; font-size: 11px; color: var(--primary); display: block; line-height: 1; }
.nav-links { display: flex; align-items: center; gap: 4px; list-style: none; }
.nav-links a { color: var(--white); text-decoration: none; font-family: 'Oswald', sans-serif; font-size: 13px; letter-spacing: 1px; padding: 8px 14px; border-radius: var(--radius-sm); transition: color .2s, background .2s; }
.nav-links a:hover { color: var(--primary); }
.nav-links a.active { color: var(--on-primary); background: var(--primary); }
.nav-logout { color: var(--text-muted); text-decoration: none; font-family: 'Oswald', sans-serif; font-size: 12px; letter-spacing: 1px; padding: 8px 14px; }
.nav-logout:hover { color: var(--danger); }

main { max-width: 1100px; margin: 0 auto; padding: 32px 24px 64px; }
.page-head { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
.page-head h1 { font-size: 26px; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); }

.card { background: var(--card-bg); border: 1px solid var(--hairline); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); }
.btn { font-family: 'Oswald', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 1px; padding: 10px 20px; border-radius: var(--radius-sm); text-decoration: none; border: none; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: var(--primary); color: var(--on-primary); }
.btn-primary:hover { background: var(--primary-hover); }
.btn-outline { background: transparent; color: var(--text-primary); border: 1.5px solid rgba(255,255,255,0.28); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { filter: brightness(1.1); }
.btn-danger { background: transparent; color: var(--danger); border: 1.5px solid rgba(222,14,14,0.4); }
.btn-danger:hover { background: rgba(222,14,14,0.12); }
.btn-delete { background: var(--danger); color: #fff; }
.btn-delete:hover { filter: brightness(1.1); }
.btn-sm { padding: 6px 14px; font-size: 12px; }
.btn:disabled { opacity: 0.35; cursor: not-allowed; pointer-events: none; }

.badge { display: inline-block; font-family: 'Oswald', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 4px 10px; border-radius: 999px; }
.badge-pending { background: rgba(249,153,5,0.15); color: var(--primary); border: 1px solid rgba(249,153,5,0.4); }
.badge-accepted { background: rgba(46,125,50,0.18); color: #6fcf73; border: 1px solid rgba(111,207,115,0.4); }
.badge-declined { background: rgba(222,14,14,0.15); color: #ff6b6b; border: 1px solid rgba(255,107,107,0.4); }

.filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-tabs a { font-family: 'Oswald', sans-serif; font-size: 12px; letter-spacing: 1px; text-transform: uppercase; text-decoration: none; color: var(--text-muted); padding: 7px 14px; border-radius: var(--radius-sm); border: 1px solid var(--hairline); }
.filter-tabs a.active, .filter-tabs a:hover { color: var(--on-primary); background: var(--primary); border-color: var(--primary); }

.table-wrap { overflow-x: auto; border-radius: var(--radius-lg); }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--hairline); font-size: 14px; white-space: nowrap; }
th { font-family: 'Oswald', sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
tbody tr:hover { background: rgba(255,255,255,0.03); }
tbody a.row-link { color: var(--text-primary); text-decoration: none; font-weight: 600; }
tbody a.row-link:hover { color: var(--primary); }
td.actions-cell { display: flex; gap: 6px; flex-wrap: nowrap; }

.empty-state { text-align: center; padding: 64px 24px; color: var(--text-muted); }

.alert { background: rgba(222,14,14,0.12); border: 1px solid rgba(222,14,14,0.4); color: #ff9d9d; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 14px; }
</style>
</head>
<body>
<nav>
  <a class="nav-logo" href="bookings.php">
    <img src="../blaya_logo.png" alt="BLAYA logo">
    <div>
      <span class="logo-text">BLAYA</span>
      <span class="logo-sub">Admin</span>
    </div>
  </a>
  <ul class="nav-links">
    <li><a href="bookings.php" class="<?= ($activeNav ?? '') === 'bookings' ? 'active' : '' ?>">Foglalások</a></li>
    <li><a href="calendar.php" class="<?= ($activeNav ?? '') === 'calendar' ? 'active' : '' ?>">Naptár</a></li>
    <li><a href="dashboard.php" class="<?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">Áttekintés</a></li>
  </ul>
  <a class="nav-logout" href="logout.php">Kijelentkezés</a>
</nav>
<main>
