<?php
// Current Visitors: everyone checked in and not yet checked out (FR-07).
// All staff. Check-out starts from here (user flow 9.2).
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/visits.php';

$user = require_login();

$search = get('q');

$sql = "SELECT v.id, v.reference, v.check_in_at, v.purpose, v.host_name,
               vi.full_name, vi.organization, d.name AS department_name, c.card_number
        FROM visits v
        JOIN visitors vi    ON vi.id = v.visitor_id
        JOIN departments d  ON d.id = v.department_id
        LEFT JOIN cards c   ON c.current_visit_id = v.id
        WHERE v.status = 'active'";
$params = [];

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $sql .= ' AND (vi.full_name LIKE ? OR v.reference LIKE ? OR c.card_number LIKE ?)';
    array_push($params, $like, $like, $like);
}

$sql .= ' ORDER BY v.check_in_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

$totalInside = count_active_visits();

$pageTitle = 'Current visitors';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head page-head-actions">
    <div>
        <h1>Current visitors</h1>
        <p class="muted"><?= $totalInside ?> visitor<?= $totalInside === 1 ? '' : 's' ?> inside the building now</p>
    </div>
    <?php if (in_array($user['role'], ['admin', 'receptionist'], true)): ?>
        <a class="pill-button pill-primary" href="<?= e(url('visits/register.php')) ?>"><?= icon('user-plus') ?>Register visitor</a>
    <?php endif; ?>
</div>

<?php if ($totalInside > 0): ?>
    <div class="toolbar">
        <form class="search search-wide" method="get" action="<?= e(url('visits/current.php')) ?>" role="search">
            <label for="q" class="sr-only">Search current visitors</label>
            <?= icon('search') ?>
            <input type="search" id="q" name="q" class="input" value="<?= e($search) ?>" placeholder="Search name, reference or card" maxlength="100">
        </form>
    </div>
<?php endif; ?>

<section class="panel panel-flush">
    <?php if (!$visits): ?>
        <div class="empty">
            <?= icon('inside') ?>
            <?php if ($totalInside === 0): ?>
                <p><strong>No visitors inside</strong></p>
                <p class="muted">Checked-in visitors appear here until they check out.</p>
            <?php else: ?>
                <p><strong>No matching visitors</strong></p>
                <p class="muted">Nobody inside matches "<?= e($search) ?>".</p>
                <a class="link-button" href="<?= e(url('visits/current.php')) ?>">Clear search</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Visitor</th>
                        <th scope="col">Visiting</th>
                        <th scope="col">Checked in</th>
                        <th scope="col">Card</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('visits/view.php?id=' . $visit['id'])) ?>"><strong><?= e($visit['full_name']) ?></strong></a>
                                <small class="block muted">
                                    <span class="mono"><?= e($visit['reference']) ?></span>
                                    <?= $visit['organization'] ? ' &middot; ' . e($visit['organization']) : '' ?>
                                </small>
                            </td>
                            <td>
                                <?= e($visit['department_name']) ?><?= $visit['host_name'] ? ' &middot; ' . e($visit['host_name']) : '' ?>
                                <small class="block muted"><?= e($visit['purpose']) ?></small>
                            </td>
                            <td class="nowrap">
                                <?= e(format_date($visit['check_in_at'], 'g:i A')) ?>
                                <small class="block muted"><?= e(format_duration(time() - strtotime($visit['check_in_at']))) ?> ago</small>
                            </td>
                            <td>
                                <?= $visit['card_number'] ? '<strong class="mono">' . e($visit['card_number']) . '</strong>' : '<span class="muted">No card</span>' ?>
                            </td>
                            <td class="cell-actions">
                                <a class="link-button" href="<?= e(url('visits/checkout.php?id=' . $visit['id'])) ?>">
                                    Check out<span class="sr-only"> <?= e($visit['full_name']) ?></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
