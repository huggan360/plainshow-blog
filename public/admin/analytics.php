<?php
// Admin: statistics from the blog's activity log.
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/views/layout.php';

use Blog\Domain\Admin;

$adminPage = 'analytics';
require dirname(__DIR__, 2) . '/app/views/admin-guard.php';
$data = Admin::analytics();
$summary = $data['summary'];
$peak = max(1, ...array_map(fn ($row) => (int) $row['total'], $data['days'] ?: [['total' => 1]]));
$months = ['01' => 'jan', '02' => 'feb', '03' => 'mar', '04' => 'apr', '05' => 'maj', '06' => 'jun', '07' => 'jul', '08' => 'aug', '09' => 'sep', '10' => 'okt', '11' => 'nov', '12' => 'dec'];

blog_layout_start(['title' => ps_t('Statistik'), 'active' => '']);
include dirname(__DIR__, 2) . '/app/views/partials/admin-nav.php';
?>
<p class="ps-muted admin-note"><?= ps_h('Sidladdningar från') ?> <?= e(blog_date($summary['first'])) ?> till <?= e(blog_date($summary['last'])) ?><?= ps_h('. Inloggningar sker numera via PlainShow och syns i') ?> <a href="<?= e(ps_url('core', '/admin/audit')) ?>">granskningsloggen</a>.</p>
<div class="member-stats">
    <span class="member-stat"><span class="member-stat__label"><?= ps_h('Sidladdningar') ?><i class="bx bx-show" aria-hidden="true"></i></span><span class="member-stat__value"><?= number_format((int) $summary['loads'], 0, ',', ' ') ?></span></span>
    <span class="member-stat"><span class="member-stat__label"><?= ps_h('Personer') ?><i class="bx bx-group" aria-hidden="true"></i></span><span class="member-stat__value"><?= (int) $summary['people'] ?></span></span>
    <span class="member-stat"><span class="member-stat__label"><?= ps_h('Unika IP') ?><i class="bx bx-globe" aria-hidden="true"></i></span><span class="member-stat__value"><?= (int) $summary['ips'] ?></span></span>
    <span class="member-stat"><span class="member-stat__label"><?= ps_h('Inloggningar') ?><i class="bx bx-log-in" aria-hidden="true"></i></span><span class="member-stat__value"><?= (int) $summary['logins'] ?></span></span>
</div>

<section class="ps-frame">
    <div class="ps-frame__panel">
        <h2 class="ps-frame__title"><?= ps_h('Aktivitet per månad') ?></h2>
        <div class="admin-chart ps-frame__body" role="img" aria-label="<?= ps_h('Sidladdningar per månad') ?>">
            <?php foreach ($data['days'] as $row): [$year, $month] = explode('-', $row['month']); ?>
                <div class="admin-chart__bar" title="<?= e($months[$month] . ' ' . $year . ': ' . $row['total']) ?>">
                    <span class="admin-chart__value"><?= (int) $row['total'] ?></span>
                    <span class="admin-chart__fill" style="height: <?= max(2, round((int) $row['total'] / $peak * 100)) ?>%"></span>
                    <span class="admin-chart__label"><?= e($months[$month]) ?><br><?= e(substr($year, 2)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="ps-frames admin-gap">
    <section class="ps-frame">
        <div class="ps-frame__panel">
            <h2 class="ps-frame__title"><?= ps_h('Sidladdningar per sida') ?></h2>
            <table class="ps-table ps-frame__body"><thead><tr><th><?= ps_h('Sida') ?></th><th><?= ps_h('Personer') ?></th><th><?= ps_h('Laddningar') ?></th></tr></thead><tbody>
                <?php foreach ($data['pages'] as $row): ?><tr><td class="is-primary"><code class="ps-code"><?= e($row['page']) ?></code></td><td><?= (int) $row['people'] ?></td><td><?= number_format((int) $row['total'], 0, ',', ' ') ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </section>
    <section class="ps-frame">
        <div class="ps-frame__panel">
            <h2 class="ps-frame__title"><?= ps_h('Sidladdningar per användare') ?></h2>
            <table class="ps-table ps-frame__body"><thead><tr><th><?= ps_h('Person') ?></th><th><?= ps_h('Senast') ?></th><th><?= ps_h('Laddningar') ?></th></tr></thead><tbody>
                <?php foreach ($data['people'] as $row): ?><tr><td class="is-primary"><span class="admin-user"><?= avatar($row, 'xs') ?><?= e(member_name($row) ?: ps_t('Okänd')) ?></span></td><td class="ps-muted"><?= e(blog_date($row['last'])) ?></td><td><?= number_format((int) $row['total'], 0, ',', ' ') ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </section>
</div>
<?php blog_layout_end();
