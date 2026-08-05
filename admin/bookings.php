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

// Same inclusive day-count formula the public booking form uses (index.html:
// days = ceil((checkout - checkin) / 1 day) + 1), so "days" always means the
// same thing across the site and the admin. One number per booking — all
// dogs on the same booking share the same date_from/date_to, so there's
// nothing to break out "per dog" here even when dogs_count > 1.
function bookingDayCount(string $dateFrom, string $dateTo): ?int {
    $from = strtotime($dateFrom);
    $to = strtotime($dateTo ?: $dateFrom);
    if (!$from || !$to) return null;
    return max(1, (int)ceil(($to - $from) / 86400)) + 1;
}

// dog_name sometimes holds every dog on the booking as a comma list (e.g.
// "Maki, Barni, Pamacs, Duna"). For the list view we only ever want the
// first name + a "(+N)" count — consistent regardless of how many names
// happen to be in the field — with the full list still available on hover.
function primaryDogName(string $dogName): string {
    $first = explode(',', $dogName, 2)[0];
    return trim($first);
}

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
  <colgroup>
    <col class="col-received" style="width: 10%;">
    <col style="width: 9%;">
    <col class="col-owner" style="width: 18%;">
    <col style="width: 11%;">
    <col style="width: 11%;">
    <col style="width: 7%;">
    <col style="width: 10%;">
    <col class="col-status" style="width: 11%;">
    <col style="width: 136px;">
  </colgroup>
  <thead>
    <tr>
      <th class="col-received">Beérkezett</th>
      <th>Kutya</th>
      <th class="col-owner">Gazda</th>
      <th>Érkezés</th>
      <th>Távozás</th>
      <th>Napok</th>
      <th>Összeg</th>
      <th class="col-status">Státusz</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($bookings as $b): ?>
    <tr>
      <td class="col-received"><?= htmlspecialchars(date('Y.m.d', strtotime($b['created_at']))) ?></td>
      <td><a class="row-link" href="booking.php?id=<?= (int)$b['id'] ?>" title="<?= htmlspecialchars($b['dog_name'] ?: '') ?>"><?= htmlspecialchars($b['dog_name'] ? primaryDogName($b['dog_name']) : '–') ?><?= $b['dogs_count'] > 1 ? ' (+' . ((int)$b['dogs_count'] - 1) . ')' : '' ?></a></td>
      <td class="col-owner" title="<?= htmlspecialchars($b['owner_name'] ?: '') ?>"><?= htmlspecialchars($b['owner_name'] ?: '–') ?></td>
      <td><?= htmlspecialchars($b['date_from'] ?: '–') ?></td>
      <td><?= htmlspecialchars($b['date_to'] ?: '–') ?></td>
      <?php $days = $b['date_from'] ? bookingDayCount($b['date_from'], $b['date_to']) : null; ?>
      <td><?= $days !== null ? $days . ' nap' : '–' ?></td>
      <td><?= number_format((float)$b['total'], 0, ',', ' ') ?> Ft</td>
      <td class="col-status"><span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($statusLabels[$b['status']] ?? $b['status']) ?></span></td>
      <td class="actions-cell">
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="action" value="accept">
          <button type="submit" class="btn btn-success btn-sm icon-btn" title="Elfogad" <?= $b['status'] === 'accepted' ? 'disabled' : '' ?>>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </button>
        </form>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="action" value="decline">
          <button type="submit" class="btn btn-danger btn-sm icon-btn" title="Elutasít" <?= $b['status'] === 'declined' ? 'disabled' : '' ?>>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Biztosan törlöd <?= htmlspecialchars(addslashes($b['dog_name'] ?: 'ezt a foglalást')) ?> foglalását? Ez nem vonható vissza.');">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn btn-delete btn-sm icon-btn" title="Töröl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
