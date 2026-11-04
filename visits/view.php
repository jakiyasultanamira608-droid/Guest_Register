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
            cs.name AS checked_out_by_name
     FROM visits v
     JOIN visitors vi    ON vi.id = v.visitor_id
     JOIN departments d  ON d.id = v.department_id
     JOIN staff rs       ON rs.id = v.registered_by
     LEFT JOIN staff cs  ON cs.id = v.checked_out_by
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
            ib.name AS issued_by_name
     FROM card_assignments ca
     JOIN cards c  ON c.id = ca.card_id
     JOIN staff ib ON ib.id = ca.issued_by
     WHERE ca.visit_id = ?'
);
$stmt->execute([$visit['id']]);
$assignment = $stmt->fetch();

$pageTitle = $visit['reference'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <p class="eyebrow mono"><?= e($visit['reference']) ?></p>
    <h1><?= e($visit['full_name']) ?></h1>
    <p class="muted">
        <span class="status status-<?= $visit['status'] === 'active' ? 'available' : 'inactive' ?>"><?= e(visit_status_label($visit['status'])) ?></span>
        &middot; <?= e($visit['department_name']) ?>
    </p>
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
                    <?php elseif ($assignment['return_status'] === 'lost'): ?>
                        <span class="status status-lost">Not returned &mdash; marked lost</span>
                    <?php else: ?>
                        <span class="muted">Not yet</span>
                    <?php endif; ?>
                </dd>
            </dl>
        <?php else: ?>
            <p class="muted">No card was issued for this visit.</p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
