<?php
// Dashboard: who is inside, today's activity, card stock and quick actions
// (PRD 10.1, FR-07). All staff.
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/visits.php';

$user      = require_login();
$canRegister = in_array($user['role'], ['admin', 'receptionist'], true);
$todayStart  = date('Y-m-d 00:00:00');

$stmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM visits WHERE status = 'active')                          AS inside,
        (SELECT COUNT(*) FROM visits WHERE status = 'active' AND check_in_at < ?)      AS inside_since_before_today,
        (SELECT COUNT(*) FROM visits WHERE check_in_at >= ?)                           AS visits_today,
        (SELECT COUNT(*) FROM visits WHERE check_out_at >= ?)                          AS checked_out_today"
);
$stmt->execute([$todayStart, $todayStart, $todayStart]);
$stats = $stmt->fetch();

$cardCounts = $pdo->query('SELECT status, COUNT(*) FROM cards GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

// The most recent arrivals still inside
$recent = $pdo->query(
    "SELECT v.id, v.reference, v.check_in_at, vi.full_name, d.name AS department_name, c.card_number
     FROM visits v
     JOIN visitors vi    ON vi.id = v.visitor_id
     JOIN departments d  ON d.id = v.department_id
     LEFT JOIN cards c   ON c.current_visit_id = v.id
     WHERE v.status = 'active'
     ORDER BY v.check_in_at DESC
     LIMIT 6"
)->fetchAll();

$inside = (int) $stats['inside'];

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head page-head-actions">
    <div>
        <h1>Dashboard</h1>
        <p class="muted">Welcome back, <?= e($user['name']) ?> &middot; <?= e(date('l, j F Y')) ?></p>
    </div>
    <div class="page-actions">
        <?php if ($canRegister): ?>
            <a class="pill-button pill-primary" href="<?= e(url('visits/register.php')) ?>"><?= icon('user-plus') ?>Register visitor</a>
        <?php endif; ?>
        <a class="pill-button" href="<?= e(url('visits/current.php')) ?>"><?= icon('inside') ?>Current visitors</a>
    </div>
</div>

<?php if ((int) $stats['inside_since_before_today'] > 0): ?>
    <div class="alert alert-warning" role="status">
        <?= icon('clock') ?>
        <span>
            <strong><?= (int) $stats['inside_since_before_today'] ?> visitor<?= (int) $stats['inside_since_before_today'] === 1 ? ' has' : 's have' ?> been inside since before today.</strong>
            Check whether they left without checking out.
            <a href="<?= e(url('visits/current.php')) ?>">Review current visitors</a>
        </span>
    </div>
<?php endif; ?>

<?php if ($user['role'] === 'admin' && !empty($cardCounts['lost'])): ?>
    <div class="alert alert-warning" role="status">
        <?= icon('card') ?>
        <span>
            <strong><?= (int) $cardCounts['lost'] ?> card<?= (int) $cardCounts['lost'] === 1 ? ' is' : 's are' ?> marked lost.</strong>
            Set found cards back to Available so they can be issued again.
            <a href="<?= e(url('cards/index.php?status=lost')) ?>">Review lost cards</a>
        </span>
    </div>
<?php endif; ?>

<section class="stats" aria-label="Today at a glance">
    <a class="stat stat-hero" href="<?= e(url('visits/current.php')) ?>">
        <span class="stat-label">Inside now</span>
        <span class="stat-value"><?= number_format($inside) ?></span>
        <span class="stat-note">visitor<?= $inside === 1 ? '' : 's' ?> checked in and not yet out</span>
    </a>

    <div class="stat-grid">
        <a class="stat" href="<?= e(url('visits/index.php?from=' . date('Y-m-d') . '&to=' . date('Y-m-d'))) ?>">
            <span class="stat-label">Visits today</span>
            <span class="stat-value"><?= number_format((int) $stats['visits_today']) ?></span>
        </a>
        <div class="stat">
            <span class="stat-label">Checked out today</span>
            <span class="stat-value"><?= number_format((int) $stats['checked_out_today']) ?></span>
        </div>
        <a class="stat" href="<?= e(url('cards/index.php?status=available')) ?>">
            <span class="stat-label">Cards available</span>
            <span class="stat-value"><?= number_format((int) ($cardCounts['available'] ?? 0)) ?></span>
        </a>
        <a class="stat" href="<?= e(url('cards/index.php?status=issued')) ?>">
            <span class="stat-label">Cards issued</span>
            <span class="stat-value"><?= number_format((int) ($cardCounts['issued'] ?? 0)) ?></span>
        </a>
    </div>
</section>

<section class="panel panel-flush">
    <div class="panel-head">
        <h2>Latest arrivals still inside</h2>
        <?php if ($inside > count($recent)): ?>
            <a class="link-button" href="<?= e(url('visits/current.php')) ?>">View all <?= $inside ?></a>
        <?php endif; ?>
    </div>

    <?php if (!$recent): ?>
        <div class="empty">
            <?= icon('inside') ?>
            <p><strong>No visitors inside</strong></p>
            <p class="muted">Checked-in visitors appear here until they check out.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Visitor</th>
                        <th scope="col">Department</th>
                        <th scope="col">Checked in</th>
                        <th scope="col">Card</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $visit): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('visits/view.php?id=' . $visit['id'])) ?>"><strong><?= e($visit['full_name']) ?></strong></a>
                                <small class="block muted mono"><?= e($visit['reference']) ?></small>
                            </td>
                            <td><?= e($visit['department_name']) ?></td>
                            <td class="nowrap">
                                <?= e(format_date($visit['check_in_at'], 'g:i A')) ?>
                                <small class="block muted"><?= e(format_duration(time() - strtotime($visit['check_in_at']))) ?> ago</small>
                            </td>
                            <td><?= $visit['card_number'] ? '<strong class="mono">' . e($visit['card_number']) . '</strong>' : '<span class="muted">No card</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
