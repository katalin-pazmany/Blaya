<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: bookings.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Érvénytelen kérés, próbáld újra.';
    } elseif (login_locked_out()) {
        $error = 'Túl sok sikertelen próbálkozás. Kérjük, próbáld újra néhány perc múlva.';
    } elseif (attempt_login($_POST['password'] ?? '')) {
        header('Location: bookings.php');
        exit;
    } else {
        $error = 'Hibás jelszó.';
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin belépés · BLAYA</title>
<link rel="icon" type="image/png" href="../paw-icon.svg">
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Lato:wght@300;400;700&family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
<style>
:root {
  --primary: #F99905;
  --primary-hover: #e88a00;
  --danger: #DE0E0E;
  --dark: #1a1a1a;
  --card-bg: #222222;
  --white: #FFFFFF;
  --text-secondary: rgba(255,255,255,0.8);
  --on-primary: #1a1a1a;
  --hairline: rgba(255,255,255,0.08);
  --radius-md: 10px;
  --radius-lg: 16px;
  --shadow-md: 0 1px 2px rgba(0,0,0,0.28), 0 6px 16px -4px rgba(0,0,0,0.40);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'Lato', sans-serif;
  background:
    linear-gradient(180deg, rgba(26,26,26,0.80) 0%, rgba(26,26,26,0.92) 55%, rgba(26,26,26,0.98) 100%),
    url('../husky_hero.jpeg') center 30% / cover no-repeat fixed;
  color: var(--text-secondary); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
}
.login-card { width: 100%; max-width: 360px; background: rgba(34,34,34,0.9); backdrop-filter: blur(6px); border: 1px solid var(--hairline); border-radius: var(--radius-lg); box-shadow: 0 2px 4px rgba(0,0,0,0.30), 0 24px 56px -12px rgba(0,0,0,0.65); padding: 36px 32px; text-align: center; }
.login-card img { width: 64px; height: 64px; object-fit: contain; margin-bottom: 8px; }
.login-card h1 { font-family: 'Oswald', sans-serif; font-size: 20px; color: var(--white); letter-spacing: 1px; margin-bottom: 4px; }
.login-card .sub { font-family: 'Dancing Script', cursive; font-size: 15px; color: var(--primary); margin-bottom: 24px; }
.password-field { position: relative; margin-bottom: 16px; }
.password-field input { width: 100%; background: rgba(255,255,255,0.05); border: 1.5px solid rgba(255,255,255,0.15); border-radius: var(--radius-md); padding: 12px 44px 12px 14px; color: var(--white); font-size: 15px; }
.password-field input:focus { outline: none; border-color: var(--primary); }
.login-card button { width: 100%; background: var(--primary); color: var(--on-primary); font-family: 'Oswald', sans-serif; font-weight: 700; letter-spacing: 1.5px; font-size: 14px; padding: 13px; border: none; border-radius: var(--radius-md); cursor: pointer; transition: background .2s; }
.login-card button:hover { background: var(--primary-hover); }
.login-card button.password-toggle { position: absolute; top: 0; right: 0; bottom: 0; width: 40px; height: auto; display: flex; align-items: center; justify-content: center; background: none; border: none; cursor: pointer; color: var(--text-secondary); padding: 0; }
.login-card button.password-toggle:hover { background: none; color: var(--white); }
.password-toggle svg { width: 20px; height: 20px; }
.password-toggle .icon-hide { display: none; }
.password-toggle.is-visible .icon-show { display: none; }
.password-toggle.is-visible .icon-hide { display: block; }
.login-error { background: rgba(222,14,14,0.12); border: 1px solid rgba(222,14,14,0.4); color: #ff9d9d; padding: 10px 14px; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 16px; }
</style>
</head>
<body>
<form class="login-card" method="post">
  <img src="../blaya_logo.png" alt="BLAYA logo">
  <h1>BLAYA ADMIN</h1>
  <div class="sub">Foglalások kezelése</div>
  <?php if ($error): ?><div class="login-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
  <div class="password-field">
    <input type="password" name="password" id="password" placeholder="Jelszó" autofocus required autocomplete="current-password">
    <button type="button" class="password-toggle" id="password-toggle" aria-label="Jelszó megjelenítése" aria-pressed="false">
      <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
      <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a13.16 13.16 0 0 1-3.22 4.19M6.61 6.61C3.86 8.36 1 12 1 12s4 8 11 8a9.26 9.26 0 0 0 5.39-1.61M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
    </button>
  </div>
  <button type="submit">Belépés</button>
</form>
<script>
(function () {
  var input = document.getElementById('password');
  var toggle = document.getElementById('password-toggle');
  toggle.addEventListener('click', function () {
    var visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    toggle.classList.toggle('is-visible', !visible);
    toggle.setAttribute('aria-pressed', String(!visible));
    toggle.setAttribute('aria-label', visible ? 'Jelszó megjelenítése' : 'Jelszó elrejtése');
  });
})();
</script>
</body>
</html>
