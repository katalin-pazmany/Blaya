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
body { font-family: 'Lato', sans-serif; background: var(--dark); color: var(--text-secondary); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.login-card { width: 100%; max-width: 360px; background: var(--card-bg); border: 1px solid var(--hairline); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); padding: 36px 32px; text-align: center; }
.login-card img { width: 64px; height: 64px; object-fit: contain; margin-bottom: 8px; }
.login-card h1 { font-family: 'Oswald', sans-serif; font-size: 20px; color: var(--white); letter-spacing: 1px; margin-bottom: 4px; }
.login-card .sub { font-family: 'Dancing Script', cursive; font-size: 15px; color: var(--primary); margin-bottom: 24px; }
.login-card input[type="password"] { width: 100%; background: rgba(255,255,255,0.05); border: 1.5px solid rgba(255,255,255,0.15); border-radius: var(--radius-md); padding: 12px 14px; color: var(--white); font-size: 15px; margin-bottom: 16px; }
.login-card input[type="password"]:focus { outline: none; border-color: var(--primary); }
.login-card button { width: 100%; background: var(--primary); color: var(--on-primary); font-family: 'Oswald', sans-serif; font-weight: 700; letter-spacing: 1.5px; font-size: 14px; padding: 13px; border: none; border-radius: var(--radius-md); cursor: pointer; transition: background .2s; }
.login-card button:hover { background: var(--primary-hover); }
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
  <input type="password" name="password" placeholder="Jelszó" autofocus required>
  <button type="submit">Belépés</button>
</form>
</body>
</html>
