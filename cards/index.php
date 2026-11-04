<?php
// Card Inventory: list of visitor ID cards (FR-10).
// All staff can view; only administrators can add or change cards.
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/cards.php';

$user    = require_login();
$isAdmin = $user['role'] === 'admin';

$status = get('status');
if (!isset(CARD_STATUSES[$status])) {
    $status = '';
}
$search = normalize_card_number(get('q'));

// Number of cards in each status, for the filter tabs
$counts = $pdo->query('SELECT status, COUNT(*) FROM cards GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$total  = array_sum($counts);

$where  = [];
$params = [];

if ($status !== '') {
    $where[]  = 'c.status = ?';
    $params[] = $status;
}

if ($search !== '') {
    // Escape LIKE wildcards so "%" and "_" are searched literally
    $where[]  = 'c.card_number LIKE ?';
    $params[] = '%' . addcslashes($search, '%_\\') . '%';
}

$sql = 'SELECT c.id, c.card_number, c.status, c.updated_at,
               s.name AS updated_by_name,
               v.id AS visit_id, v.reference, v.check_in_at, vi.full_name AS visitor_name
        FROM cards c
        LEFT JOIN staff s     ON s.id = c.updated_by
        LEFT JOIN visits v    ON v.id = c.current_visit_id
        LEFT JOIN visitors vi ON vi.id = v.visitor_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY LENGTH(c.card_number), c.card_number';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cards = $stmt->fetchAll();

// Link to this page with a given status filter, keeping the search text
function cards_filter_url(string $status, string $search): string
{
    $query = array_filter(['status' => $status, 'q' => $search], 'strlen');

    return url('cards/index.php' . ($query ? '?' . http_build_query($query) : ''));
}

$pageTitle = 'Cards';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head page-head-actions">
    <div>
        <h1>Cards</h1>
        <p class="muted">Visitor ID cards and who is holding them</p>
    </div>
    <?php if ($isAdmin): ?>
        <a class="pill-button pill-primary" href="<?= e(url('cards/create.php')) ?>"><?= icon('plus') ?>Add card</a>
    <?php endif; ?>
</div>

<div class="toolbar">
    <nav class="tabs" aria-label="Filter by status">
        <a href="<?= e(cards_filter_url('', $search)) ?>"<?= $status === '' ? ' class="active" aria-current="true"' : '' ?>>
            All <span class="count"><?= $total ?></span>
        </a>
        <?php foreach (CARD_STATUSES as $key => $label): ?>
            <a href="<?= e(cards_filter_url($key, $search)) ?>"<?= $status === $key ? ' class="active" aria-current="true"' : '' ?>>
                <?= e($label) ?> <span class="count"><?= (int) ($counts[$key] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="search" method="get" action="<?= e(url('cards/index.php')) ?>" role="search">
        <?php if ($status !== ''): ?>
            <input type="hidden" name="status" value="<?= e($status) ?>">
        <?php endif; ?>
        <label for="q" class="sr-only">Search card number</label>
        <?= icon('search') ?>
        <input type="search" id="q" name="q" class="input" value="<?= e($search) ?>" placeholder="Search card number" maxlength="20">
    </form>
</div>

<section class="panel panel-flush">
    <?php if (!$cards): ?>
        <div class="empty">
            <?= icon('card') ?>
            <?php if ($total === 0): ?>
                <p><strong>No cards yet</strong></p>
                <p class="muted">Cards appear here once an administrator adds them.</p>
            <?php else: ?>
                <p><strong>No matching cards</strong></p>
                <p class="muted">Try a different search or status.</p>
                <a class="link-button" href="<?= e(url('cards/index.php')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Card</th>
                        <th scope="col">Status</th>
                        <th scope="col">Held by</th>
                        <th scope="col">Last changed</th>
                        <?php if ($isAdmin): ?>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cards as $card): ?>
                        <tr>
                            <td><strong class="mono"><?= e($card['card_number']) ?></strong></td>
                            <td><span class="status status-<?= e($card['status']) ?>"><?= e(card_status_label($card['status'])) ?></span></td>
                            <td>
                                <?php if ($card['status'] === 'issued' && $card['visitor_name']): ?>
                                    <a href="<?= e(url('visits/view.php?id=' . $card['visit_id'])) ?>"><?= e($card['visitor_name']) ?></a>
                                    <small class="block muted"><?= e($card['reference']) ?> &middot; since <?= e(format_date($card['check_in_at'], 'g:i A')) ?></small>
                                <?php else: ?>
                                    <span class="muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap">
                                <?= e(format_date($card['updated_at'], 'j M Y, g:i A')) ?>
                                <?php if ($card['updated_by_name']): ?>
                                    <small class="block muted">by <?= e($card['updated_by_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td class="cell-actions">
                                    <?php if ($card['status'] === 'issued'): ?>
                                        <span class="locked" title="Issued cards are freed by checking the visitor out">
                                            <?= icon('lock') ?><span class="sr-only">Locked while issued</span>
                                        </span>
                                    <?php else: ?>
                                        <a class="link-button" href="<?= e(url('cards/edit.php?id=' . $card['id'])) ?>">
                                            Edit<span class="sr-only"> card <?= e($card['card_number']) ?></span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
