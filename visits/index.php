<?php
// Visit History: search and filter all saved visits (FR-08). All staff.
// Newest first, 20 per page.
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/visits.php';
require __DIR__ . '/../includes/reports.php';

$user = require_login();

const HISTORY_PAGE_SIZE = 20;

$filters = [
    'q'          => get('q'),
    'from'       => date_param('from'),
    'to'         => date_param('to'),
    'department' => ctype_digit(get('department')) ? get('department') : '',
    'status'     => in_array(get('status'), ['active', 'completed', 'card_lost'], true) ? get('status') : '',
    'card'       => mb_strtoupper(get('card')),
];

$dateError = '';
if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
    $dateError = 'The "From" date is after the "To" date, so no visits can match.';
}

$where  = [];
$params = [];

if ($filters['q'] !== '') {
    $like     = '%' . addcslashes($filters['q'], '%_\\') . '%';
    $where[]  = '(vi.full_name LIKE ? OR v.reference LIKE ? OR vi.organization LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($filters['from'] !== '') {
    $where[]  = 'v.check_in_at >= ?';
    $params[] = $filters['from'] . ' 00:00:00';
}
if ($filters['to'] !== '') {
    $where[]  = 'v.check_in_at < ? + INTERVAL 1 DAY';
    $params[] = $filters['to'];
}
if ($filters['department'] !== '') {
    $where[]  = 'v.department_id = ?';
    $params[] = (int) $filters['department'];
}
if ($filters['status'] === 'card_lost') {
    $where[] = "ca.return_status = 'lost'";
} elseif ($filters['status'] !== '') {
    $where[]  = 'v.status = ?';
    $params[] = $filters['status'];
}
if ($filters['card'] !== '') {
    $where[]  = 'c.card_number LIKE ?';
    $params[] = '%' . addcslashes($filters['card'], '%_\\') . '%';
}

$from = ' FROM visits v
          JOIN visitors vi             ON vi.id = v.visitor_id
          JOIN departments d           ON d.id = v.department_id
          LEFT JOIN card_assignments ca ON ca.visit_id = v.id
          LEFT JOIN cards c            ON c.id = ca.card_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

$stmt = $pdo->prepare('SELECT COUNT(*)' . $from);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pages = max(1, (int) ceil($total / HISTORY_PAGE_SIZE));
$page  = min($pages, max(1, (int) get('page')));

$stmt = $pdo->prepare(
    'SELECT v.id, v.reference, v.status, v.check_in_at, v.check_out_at, v.purpose, v.host_name,
            vi.full_name, vi.organization, d.name AS department_name,
            c.card_number, ca.return_status'
    . $from
    . ' ORDER BY v.check_in_at DESC, v.id DESC LIMIT ' . HISTORY_PAGE_SIZE . ' OFFSET ' . (($page - 1) * HISTORY_PAGE_SIZE)
);
$stmt->execute($params);
$visits = $stmt->fetchAll();

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
$hasFilters  = (bool) array_filter($filters, 'strlen');

// Link to another page of results, keeping the current filters
function history_page_url(array $filters, int $page): string
{
    $query = array_filter($filters + ['page' => $page > 1 ? (string) $page : ''], 'strlen');

    return url('visits/index.php' . ($query ? '?' . http_build_query($query) : ''));
}

$pageTitle = 'Visit history';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Visit history</h1>
    <p class="muted">Every recorded visit, newest first</p>
</div>

<form class="filters panel" method="get" action="<?= e(url('visits/index.php')) ?>" role="search">
    <div class="field filter-search">
        <label for="q">Visitor or reference</label>
        <input type="search" id="q" name="q" class="input" value="<?= e($filters['q']) ?>" placeholder="Name, company or GR-..." maxlength="100">
    </div>
    <div class="field">
        <label for="from">From</label>
        <input type="date" id="from" name="from" class="input" value="<?= e($filters['from']) ?>">
    </div>
    <div class="field">
        <label for="to">To</label>
        <input type="date" id="to" name="to" class="input" value="<?= e($filters['to']) ?>">
    </div>
    <div class="field">
        <label for="department">Department</label>
        <select id="department" name="department" class="input">
            <option value="">All departments</option>
            <?php foreach ($departments as $department): ?>
                <option value="<?= (int) $department['id'] ?>"<?= $filters['department'] === (string) $department['id'] ? ' selected' : '' ?>><?= e($department['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" class="input">
            <option value="">All visits</option>
            <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Checked in</option>
            <option value="completed"<?= $filters['status'] === 'completed' ? ' selected' : '' ?>>Checked out</option>
            <option value="card_lost"<?= $filters['status'] === 'card_lost' ? ' selected' : '' ?>>Card not returned</option>
        </select>
    </div>
    <div class="field">
        <label for="card">Card</label>
        <input type="text" id="card" name="card" class="input mono" value="<?= e($filters['card']) ?>" placeholder="V-001" maxlength="20">
    </div>
    <div class="filter-actions">
        <button type="submit" class="pill-button pill-primary">Apply</button>
        <?php if ($hasFilters): ?>
            <a class="pill-button" href="<?= e(url('visits/index.php')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($dateError): ?>
    <div class="alert alert-info" role="status"><?= e($dateError) ?></div>
<?php endif; ?>

<p class="result-count muted">
    <?php if ($total === 0): ?>
        No visits
    <?php else: ?>
        Showing <?= ($page - 1) * HISTORY_PAGE_SIZE + 1 ?>&ndash;<?= min($total, $page * HISTORY_PAGE_SIZE) ?> of <?= $total ?> visit<?= $total === 1 ? '' : 's' ?>
    <?php endif; ?>
</p>

<section class="panel panel-flush">
    <?php if (!$visits): ?>
        <div class="empty">
            <?= icon('history') ?>
            <?php if ($hasFilters): ?>
                <p><strong>No matching visits</strong></p>
                <p class="muted">Try a different name, date range or filter.</p>
                <a class="link-button" href="<?= e(url('visits/index.php')) ?>">Clear filters</a>
            <?php else: ?>
                <p><strong>No visits yet</strong></p>
                <p class="muted">Visits appear here once visitors are registered.</p>
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
                        <th scope="col">Checked out</th>
                        <th scope="col">Card</th>
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
                                <?= e(format_date($visit['check_in_at'], 'j M Y')) ?>
                                <small class="block muted"><?= e(format_date($visit['check_in_at'], 'g:i A')) ?></small>
                            </td>
                            <td class="nowrap">
                                <?php if ($visit['status'] === 'active'): ?>
                                    <span class="status status-available">Inside</span>
                                <?php else: ?>
                                    <?= e(format_date($visit['check_out_at'], 'g:i A')) ?>
                                    <small class="block muted"><?= e(format_duration(strtotime($visit['check_out_at']) - strtotime($visit['check_in_at']))) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap">
                                <?php if ($visit['card_number']): ?>
                                    <strong class="mono"><?= e($visit['card_number']) ?></strong>
                                    <?php if ($visit['return_status'] === 'lost'): ?>
                                        <small class="block"><span class="status status-lost">Not returned</span></small>
                                    <?php elseif ($visit['return_status'] === 'returned'): ?>
                                        <small class="block muted">Returned</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($page > 1): ?>
            <a class="pill-button" href="<?= e(history_page_url($filters, $page - 1)) ?>">Previous</a>
        <?php endif; ?>
        <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?>
            <a class="pill-button" href="<?= e(history_page_url($filters, $page + 1)) ?>">Next</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
