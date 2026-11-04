<?php
// Reports: visitor activity for a date range (FR-11, US-09).
// Administrators and receptionists. All totals are counted from saved visits.
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/visits.php';
require __DIR__ . '/../includes/reports.php';

$user = require_role('admin', 'receptionist');

$today   = date('Y-m-d');
$presets = [
    'Today'        => [$today, $today],
    'Last 7 days'  => [date('Y-m-d', strtotime('-6 days')), $today],
    'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), $today],
    'This month'   => [date('Y-m-01'), $today],
];

$from       = date_param('from') ?: $presets['Last 7 days'][0];
$to         = date_param('to') ?: $today;
$department = ctype_digit(get('department')) ? get('department') : '';
$notice     = '';

if ($from > $to) {
    [$from, $to] = [$to, $from];
    $notice = 'The dates were the wrong way round, so they have been swapped.';
}

if (count(report_days($from, $to)) > REPORT_MAX_DAYS) {
    $from   = date('Y-m-d', strtotime($to . ' -' . (REPORT_MAX_DAYS - 1) . ' days'));
    $notice = 'Reports cover at most one year, so the start date was moved to ' . format_date($from) . '.';
}

// Shared WHERE clause: visits that checked in during the period
$where  = 'v.check_in_at >= ? AND v.check_in_at < ? + INTERVAL 1 DAY';
$params = [$from . ' 00:00:00', $to];
if ($department !== '') {
    $where   .= ' AND v.department_id = ?';
    $params[] = (int) $department;
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*)                                         AS visits,
            COUNT(DISTINCT v.visitor_id)                     AS visitors,
            SUM(v.status = 'completed')                      AS completed,
            SUM(v.status = 'active')                         AS active,
            COUNT(ca.id)                                     AS cards_issued,
            SUM(ca.return_status = 'lost')                   AS cards_lost,
            AVG(TIMESTAMPDIFF(SECOND, v.check_in_at, v.check_out_at)) AS avg_stay
     FROM visits v
     LEFT JOIN card_assignments ca ON ca.visit_id = v.id
     WHERE $where"
);
$stmt->execute($params);
$summary = array_map('intval', $stmt->fetch());

$stmt = $pdo->prepare(
    "SELECT DATE(v.check_in_at)              AS day,
            COUNT(*)                         AS visits,
            SUM(v.status = 'completed')      AS completed,
            SUM(v.status = 'active')         AS active,
            COUNT(ca.id)                     AS cards_issued,
            SUM(ca.return_status = 'lost')   AS cards_lost
     FROM visits v
     LEFT JOIN card_assignments ca ON ca.visit_id = v.id
     WHERE $where
     GROUP BY DATE(v.check_in_at)"
);
$stmt->execute($params);
$byDay = [];
foreach ($stmt->fetchAll() as $row) {
    $byDay[$row['day']] = array_map('intval', $row);
}

// Every day in the period, including days with no visits
$empty = ['visits' => 0, 'completed' => 0, 'active' => 0, 'cards_issued' => 0, 'cards_lost' => 0];
$days  = [];
foreach (report_days($from, $to) as $day) {
    $days[$day] = $byDay[$day] ?? $empty;
}

$stmt = $pdo->prepare(
    "SELECT d.name, COUNT(*) AS visits
     FROM visits v
     JOIN departments d ON d.id = v.department_id
     WHERE $where
     GROUP BY d.id, d.name
     ORDER BY visits DESC, d.name"
);
$stmt->execute($params);
$byDepartment = $stmt->fetchAll();

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

// Chart scale
$visitsPerDay = array_combine(array_keys($days), array_column($days, 'visits'));
$busiest      = max($visitsPerDay);
$busiestDate  = $busiest > 0 ? array_search($busiest, $visitsPerDay, true) : null;
[$axisMax, $axisStep] = nice_axis($busiest);
$labelEvery  = max(1, (int) ceil(count($days) / 10));   // at most ~10 date labels

// Long periods: hide empty days in the table to keep it readable
$hideEmptyDays = count($days) > 31;

$historyLink = url('visits/index.php?' . http_build_query(array_filter(
    ['from' => $from, 'to' => $to, 'department' => $department],
    'strlen'
)));

$periodLabel = $from === $to
    ? format_date($from, 'l, j F Y')
    : format_date($from, 'j M Y') . ' – ' . format_date($to, 'j M Y');

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head page-head-actions">
    <div>
        <h1>Reports</h1>
        <p class="muted">Visitor activity for <?= e($periodLabel) ?></p>
    </div>
    <div class="page-actions no-print">
        <a class="pill-button" href="<?= e($historyLink) ?>"><?= icon('history') ?>View these visits</a>
        <button type="button" class="pill-button" data-print><?= icon('print') ?>Print</button>
    </div>
</div>

<form class="report-filters no-print" method="get" action="<?= e(url('reports/index.php')) ?>">
    <nav class="tabs" aria-label="Quick date ranges">
        <?php foreach ($presets as $label => [$presetFrom, $presetTo]): ?>
            <?php $isActive = $from === $presetFrom && $to === $presetTo; ?>
            <a href="<?= e(url('reports/index.php?' . http_build_query(array_filter(['from' => $presetFrom, 'to' => $presetTo, 'department' => $department], 'strlen')))) ?>"
               <?= $isActive ? 'class="active" aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="report-range">
        <label for="from" class="sr-only">From</label>
        <input type="date" id="from" name="from" class="input" value="<?= e($from) ?>">
        <span class="muted">to</span>
        <label for="to" class="sr-only">To</label>
        <input type="date" id="to" name="to" class="input" value="<?= e($to) ?>">
        <label for="department" class="sr-only">Department</label>
        <select id="department" name="department" class="input">
            <option value="">All departments</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= (int) $dept['id'] ?>"<?= $department === (string) $dept['id'] ? ' selected' : '' ?>><?= e($dept['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="pill-button pill-primary">Apply</button>
    </div>
</form>

<?php if ($notice): ?>
    <div class="alert alert-info" role="status"><?= e($notice) ?></div>
<?php endif; ?>

<section class="kpis" aria-label="Summary">
    <div class="stat">
        <span class="stat-label">Visits</span>
        <span class="stat-value"><?= number_format($summary['visits']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Different visitors</span>
        <span class="stat-value"><?= number_format($summary['visitors']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Checked out</span>
        <span class="stat-value"><?= number_format($summary['completed']) ?></span>
        <span class="stat-note"><?= number_format($summary['active']) ?> still inside</span>
    </div>
    <div class="stat">
        <span class="stat-label">Average stay</span>
        <span class="stat-value"><?= $summary['completed'] ? e(format_duration($summary['avg_stay'])) : '&mdash;' ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Cards not returned</span>
        <span class="stat-value"><?= number_format($summary['cards_lost']) ?></span>
        <span class="stat-note">of <?= number_format($summary['cards_issued']) ?> issued</span>
    </div>
</section>

<?php if ($summary['visits'] === 0): ?>

    <section class="panel">
        <div class="empty">
            <?= icon('chart') ?>
            <p><strong>No visits in this period</strong></p>
            <p class="muted">Nobody checked in between <?= e(format_date($from)) ?> and <?= e(format_date($to)) ?><?= $department !== '' ? ' for this department' : '' ?>. Try a longer date range.</p>
        </div>
    </section>

<?php else: ?>

    <div class="report-grid">
        <section class="panel">
            <h2 id="daily-title">Visits per day</h2>
            <p class="muted chart-sub">Busiest day: <?= e(format_date($busiestDate, 'D j M')) ?> with <?= $busiest ?> visit<?= $busiest === 1 ? '' : 's' ?></p>

            <figure class="chart" aria-labelledby="daily-title">
                <div class="chart-plot">
                    <?php for ($tick = 0; $tick <= $axisMax; $tick += $axisStep): ?>
                        <div class="gridline" style="bottom: <?= $tick / $axisMax * 100 ?>%"><span><?= $tick ?></span></div>
                    <?php endfor; ?>

                    <div class="columns" style="grid-template-columns: repeat(<?= count($days) ?>, 1fr)">
                        <?php foreach ($days as $day => $row): ?>
                            <?php $label = format_date($day, 'D j M') . ': ' . $row['visits'] . ' visit' . ($row['visits'] === 1 ? '' : 's'); ?>
                            <div class="col" tabindex="0" aria-label="<?= e($label) ?>">
                                <?php if ($row['visits'] > 0): ?>
                                    <span class="bar" style="height: <?= $row['visits'] / $axisMax * 100 ?>%"></span>
                                <?php endif; ?>
                                <?php if ($day === $busiestDate): ?>
                                    <span class="bar-label" style="bottom: <?= $row['visits'] / $axisMax * 100 ?>%"><?= $row['visits'] ?></span>
                                <?php endif; ?>
                                <span class="chart-tip" aria-hidden="true">
                                    <strong><?= $row['visits'] ?> visit<?= $row['visits'] === 1 ? '' : 's' ?></strong>
                                    <span><?= e(format_date($day, 'D j M Y')) ?></span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="x-labels" style="grid-template-columns: repeat(<?= count($days) ?>, 1fr)" aria-hidden="true">
                    <?php $i = 0; foreach ($days as $day => $row): ?>
                        <span><?= $i++ % $labelEvery === 0 ? e(format_date($day, count($days) > 14 ? 'j M' : 'D j')) : '' ?></span>
                    <?php endforeach; ?>
                </div>
            </figure>
        </section>

        <section class="panel">
            <h2>Visits by department</h2>
            <p class="muted chart-sub">Largest first</p>

            <ul class="ranked">
                <?php $topDepartment = (int) $byDepartment[0]['visits']; ?>
                <?php foreach ($byDepartment as $row): ?>
                    <li>
                        <span class="ranked-name"><?= e($row['name']) ?></span>
                        <span class="ranked-bar-wrap">
                            <span class="ranked-bar" style="width: calc((100% - 4.5rem) * <?= round((int) $row['visits'] / $topDepartment, 4) ?>)"></span>
                            <span class="ranked-value">
                                <?= (int) $row['visits'] ?>
                                <small><?= round((int) $row['visits'] / $summary['visits'] * 100) ?>%</small>
                            </span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <section class="panel panel-flush">
        <div class="panel-head">
            <h2>Daily breakdown</h2>
            <?php if ($hideEmptyDays): ?>
                <span class="muted">Days without visits are hidden</span>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="table table-numbers">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Visits</th>
                        <th scope="col">Checked out</th>
                        <th scope="col">Still inside</th>
                        <th scope="col">Cards issued</th>
                        <th scope="col">Not returned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($days, true) as $day => $row): ?>
                        <?php if ($hideEmptyDays && $row['visits'] === 0) continue; ?>
                        <tr<?= $row['visits'] === 0 ? ' class="row-muted"' : '' ?>>
                            <th scope="row"><?= e(format_date($day, 'D j M Y')) ?></th>
                            <td><?= $row['visits'] ?></td>
                            <td><?= $row['completed'] ?></td>
                            <td><?= $row['active'] ?></td>
                            <td><?= $row['cards_issued'] ?></td>
                            <td><?= $row['cards_lost'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Total</th>
                        <td><?= $summary['visits'] ?></td>
                        <td><?= $summary['completed'] ?></td>
                        <td><?= $summary['active'] ?></td>
                        <td><?= $summary['cards_issued'] ?></td>
                        <td><?= $summary['cards_lost'] ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
