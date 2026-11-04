<?php
// Visit details: visitor, visit, times and card (FR-08). All staff.
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/visits.php';

$user = require_login();

$visitId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$stmt = $pdo->prepare(
    'SELECT v.*, vi.full_name, vi.organization, vi.contact_number,
            d.name AS department_name,
            rs.name AS registered_by_name,
            cs.name AS checked_out_by_name,
            es.name AS edited_by_name
     FROM visits v
     JOIN visitors vi    ON vi.id = v.visitor_id
     JOIN departments d  ON d.id = v.department_id
     JOIN staff rs       ON rs.id = v.registered_by
     LEFT JOIN staff cs  ON cs.id = v.checked_out_by
     LEFT JOIN staff es  ON es.id = v.edited_by
     WHERE v.id = ?'
);
$stmt->execute([$visitId]);
$visit = $stmt->fetch();

if (!$visit) {
    http_response_code(404);
    $pageTitle = 'Visit not found';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="panel"><h1>Visit not found</h1>'
        . '<p class="muted">This visit does not exist.</p>'
        . '<a class="pill-button pill-primary" href="' . e(url('dashboard.php')) . '">Back to dashboard</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare(
    'SELECT c.card_number, ca.issued_at, ca.returned_at, ca.return_status, ca.note,
            ib.name AS issued_by_name, rb.name AS received_by_name
     FROM card_assignments ca
     JOIN cards c       ON c.id = ca.card_id
     JOIN staff ib      ON ib.id = ca.issued_by
     LEFT JOIN staff rb ON rb.id = ca.received_by
     WHERE ca.visit_id = ?'
);
$stmt->execute([$visit['id']]);
$assignment = $stmt->fetch();

$pageTitle = $visit['reference'];
require __DIR__ . '/../includes/header.php';
?>

<?php
$isActive = $visit['status'] === 'active';
$duration = format_duration(($isActive ? time() : strtotime($visit['check_out_at'])) - strtotime($visit['check_in_at']));
?>

<div class="page-head page-head-actions">
    <div>
        <p class="eyebrow mono"><?= e($visit['reference']) ?></p>
        <h1><?= e($visit['full_name']) ?></h1>
        <p class="muted">
            <span class="status status-<?= $isActive ? 'available' : 'inactive' ?>"><?= e(visit_status_label($visit['status'])) ?></span>
            &middot; <?= e($visit['department_name']) ?>
            &middot; <?= $isActive ? 'inside for ' : 'stayed ' ?><?= e($duration) ?>
        </p>
    </div>
    <div class="page-actions">
        <?php if (in_array($user['role'], ['admin', 'receptionist'], true)): ?>
            <a class="pill-button" href="<?= e(url('visits/edit.php?id=' . $visit['id'])) ?>">Edit</a>
        <?php endif; ?>
        <?php if ($isActive): ?>
            <a class="pill-button pill-primary" href="<?= e(url('visits/checkout.php?id=' . $visit['id'])) ?>"><?= icon('logout') ?>Check out</a>
        <?php endif; ?>
    </div>
</div>

<div class="panel-grid">
    <section class="panel">
        <h2>Visitor</h2>
        <dl class="details">
            <dt>Name</dt>
            <dd><?= e($visit['full_name']) ?></dd>
            <dt>Company</dt>
            <dd><?= $visit['organization'] ? e($visit['organization']) : '<span class="muted">&mdash;</span>' ?></dd>
            <dt>Phone</dt>
            <dd><?= $visit['contact_number'] ? e($visit['contact_number']) : '<span class="muted">&mdash;</span>' ?></dd>
        </dl>
    </section>

    <section class="panel">
        <h2>Visit</h2>
        <dl class="details">
            <dt>Department</dt>
            <dd><?= e($visit['department_name']) ?></dd>
            <dt>Visiting</dt>
            <dd><?= $visit['host_name'] ? e($visit['host_name']) : '<span class="muted">&mdash;</span>' ?></dd>
            <dt>Purpose</dt>
            <dd><?= e($visit['purpose']) ?></dd>
            <?php if ($visit['notes']): ?>
                <dt>Notes</dt>
                <dd><?= nl2br(e($visit['notes'])) ?></dd>
            <?php endif; ?>
        </dl>
    </section>

    <section class="panel">
        <h2>Times</h2>
        <dl class="details">
            <dt>Checked in</dt>
            <dd>
                <?= e(format_date($visit['check_in_at'], 'j M Y, g:i A')) ?>
                <small class="block muted">by <?= e($visit['registered_by_name']) ?></small>
            </dd>
            <dt>Checked out</dt>
            <dd>
                <?php if ($visit['check_out_at']): ?>
                    <?= e(format_date($visit['check_out_at'], 'j M Y, g:i A')) ?>
                    <?php if ($visit['checked_out_by_name']): ?>
                        <small class="block muted">by <?= e($visit['checked_out_by_name']) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="muted">Still inside</span>
                <?php endif; ?>
            </dd>
            <?php if ($visit['edited_at']): ?>
                <dt>Last edited</dt>
                <dd>
                    <?= e(format_date($visit['edited_at'], 'j M Y, g:i A')) ?>
                    <small class="block muted">by <?= e($visit['edited_by_name']) ?></small>
                </dd>
            <?php endif; ?>
        </dl>
    </section>

    <section class="panel">
        <h2>Visitor card</h2>
        <?php if ($assignment): ?>
            <dl class="details">
                <dt>Card</dt>
                <dd><strong class="mono"><?= e($assignment['card_number']) ?></strong></dd>
                <dt>Issued</dt>
                <dd>
                    <?= e(format_date($assignment['issued_at'], 'g:i A')) ?>
                    <small class="block muted">by <?= e($assignment['issued_by_name']) ?></small>
                </dd>
                <dt>Returned</dt>
                <dd>
                    <?php if ($assignment['return_status'] === 'returned'): ?>
                        <?= e(format_date($assignment['returned_at'], 'g:i A')) ?>
                        <small class="block muted">to <?= e($assignment['received_by_name']) ?></small>
                    <?php elseif ($assignment['return_status'] === 'lost'): ?>
                        <span class="status status-lost">Not returned &mdash; marked lost</span>
                        <small class="block muted">recorded by <?= e($assignment['received_by_name']) ?></small>
                    <?php else: ?>
                        <span class="muted">Not yet</span>
                    <?php endif; ?>
                </dd>
                <?php if ($assignment['note']): ?>
                    <dt>Note</dt>
                    <dd><?= e($assignment['note']) ?></dd>
                <?php endif; ?>
            </dl>
        <?php else: ?>
            <p class="muted">No card was issued for this visit.</p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
