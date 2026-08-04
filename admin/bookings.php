<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        die('Érvénytelen kérés.');
    }
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && $action === 'delete') {
        deleteBooking($id);
    } else {
        $newStatus = ['accept' => 'accepted', 'decline' => 'declined'][$action] ?? null;
        if ($id && $newStatus) {
            updateBookingStatus($id, $newStatus);
        }
    }
    header('Location: bookings.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['pending', 'accepted', 'declined'];
$statusFilter = in_array($statusFilter, $validStatuses, true) ? $statusFilter : null;

$bookings = getBookings($statusFilter);
$csrf = csrf_token();

$statusLabels = ['pending' => 'Függőben', 'accepted' => 'Elfogadva', 'declined' => 'Elutasítva'];

$pageTitle = 'Foglalások';
$activeNav = 'bookings';
require __DIR__ . '/includes/layout_header.php';
?>

<div class="page-head">
  <h1>Foglalások</h1>
</div>

<div class="filter-tabs">
  <a href="bookings.php" class="<?= $statusFilter === null ? 'active' : '' ?>">Mind</a>
  <a href="bookings.php?status=pending" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Függőben</a>
  <a href="bookings.php?status=accepted" class="<?= $statusFilter === 'accepted' ? 'active' : '' ?>">Elfogadva</a>
  <a href="bookings.php?status=declined" class="<?= $statusFilter === 'declined' ? 'active' : '' ?>">Elutasítva</a>
</div>

<?php if (empty($bookings)): ?>
  <div class="card empty-state">Nincs ide tartozó foglalás.</div>
<?php else: ?>
<div class="card table-wrap">
<table>
  <thead>
    <tr>
      <th>Beérkezett</th>
      <th>Kutya</th>
      <th>Gazda</th>
      <th>Érkezés</th>
      <th>Távozás</th>
      <th>Összeg</th>
      <th>Státusz</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($bookings as $b): ?>
    <tr>
      <td><?= htmlspecialchars(date('Y.m.d H:i', strtotime($b['created_at']))) ?></td>
      <td><a class="row-link" href="booking.php?id=<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['dog_name'] ?: '–') ?><?= $b['dogs_count'] > 1 ? ' (+' . ((int)$b['dogs_count'] - 1) . ')' : '' ?></a></td>
      <td><?= htmlspecialchars($b['owner_name'] ?: '–') ?></td>
      <td><?= htmlspecialchars($b['date_from'] ?: '–') ?></td>
      <td><?= htmlspecialchars($b['date_to'] ?: '–') ?></td>
      <td><?= number_format((float)$b['total'], 0, ',', ' ') ?> Ft</td>
      <td><span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($statusLabels[$b['status']] ?? $b['status']) ?></span></td>
      <td class="actions-cell">
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="action" value="accept">
          <button type="submit" class="btn btn-success btn-sm" <?= $b['status'] === 'accepted' ? 'disabled' : '' ?>>Elfogad</button>
        </form>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="action" value="decline">
          <button type="submit" class="btn btn-danger btn-sm" <?= $b['status'] === 'declined' ? 'disabled' : '' ?>>Elutasít</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Biztosan törlöd <?= htmlspecialchars(addslashes($b['dog_name'] ?: 'ezt a foglalást')) ?> foglalását? Ez nem vonható vissza.');">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn btn-delete btn-sm">Töröl</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
