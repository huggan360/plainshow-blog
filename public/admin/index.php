<?php
// Admin: every post, with filters, visibility and delete.
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/views/layout.php';

use Blog\Domain\Admin;

$adminPage = 'posts';
if (!Admin::can(current_identity(), 'posts')) {
    // People who only send notices land on their page.
    foreach (array_keys(Admin::PAGES) as $page) {
        if (Admin::can(current_identity(), $page)) {
            redirect_to(Admin::PAGES[$page][2]);
        }
    }
}
require dirname(__DIR__, 2) . '/app/views/admin-guard.php';

$filters = ['all' => 'Alla', 'public' => 'Offentliga', 'hidden' => 'Dolda', 'today' => ps_t('Nya idag')];
$filter = isset($filters[$_GET['filter'] ?? '']) ? $_GET['filter'] : 'all';
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$counts = Admin::postCounts();
$result = Admin::posts($filter, $search, $page);
$base = ['filter' => $filter === 'all' ? null : $filter, 'q' => $search !== '' ? $search : null];
$link = function (array $change) use ($base): string {
    $query = http_build_query(array_filter(array_merge($base, $change), fn ($value) => $value !== null && $value !== ''));
    return '/admin' . ($query !== '' ? '?' . $query : '');
};

blog_layout_start(['title' => ps_t('Admin'), 'active' => '']);
include dirname(__DIR__, 2) . '/app/views/partials/admin-nav.php';
?>
<div class="member-stats">
    <a class="member-stat" href="/admin"><span class="member-stat__label"><?= ps_h('Alla inlägg') ?><i class="bx bx-collection" aria-hidden="true"></i></span><span class="member-stat__value"><?= (int) $counts['total'] ?></span></a>
    <a class="member-stat" href="/admin?filter=public"><span class="member-stat__label"><?= ps_h('Offentliga') ?><i class="bx bx-world" aria-hidden="true"></i></span><span class="member-stat__value"><?= (int) $counts['public'] ?></span></a>
    <a class="member-stat" href="/admin?filter=hidden"><span class="member-stat__label"><?= ps_h('Bara inloggade') ?><i class="bx bx-lock-alt" aria-hidden="true"></i></span><span class="member-stat__value"><?= (int) $counts['hidden'] ?></span></a>
    <span class="member-stat"><span class="member-stat__label"><?= ps_h('Kommentarer') ?><i class="bx bx-comment" aria-hidden="true"></i></span><span class="member-stat__value"><?= (int) $counts['comments'] ?></span></span>
</div>

<section class="ps-frame">
    <div class="ps-frame__panel admin-posts">
        <form class="admin-filter" method="get" action="/admin">
            <nav class="ps-tabs" aria-label="<?= ps_h('Filtrera') ?>">
                <?php foreach ($filters as $key => $label): ?>
                    <a class="ps-tab<?= $filter === $key ? ' is-active' : '' ?>" href="<?= e($link(['filter' => $key === 'all' ? null : $key, 'page' => null])) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
            <div class="admin-filter__search"><i class="bx bx-search" aria-hidden="true"></i><input class="ps-input" type="search" name="q" value="<?= e($search) ?>" placeholder="<?= ps_h('Sök titel eller skapare') ?>" aria-label="<?= ps_h('Sök inlägg') ?>"></div>
        </form>
        <?php if ($result['rows']): ?>
            <div class="ps-table-wrap">
                <table class="ps-table admin-table">
                    <thead><tr><th><?= ps_h('Inlägg') ?></th><th class="admin-hide-sm"><?= ps_h('Skapare') ?></th><th class="admin-hide-sm"><?= ps_h('Datum') ?></th><th><?= ps_h('Synlighet') ?></th><th class="admin-hide-md"><span class="ps-sr-only"><?= ps_h('Reaktioner') ?></span></th><th><span class="ps-sr-only"><?= ps_h('Åtgärder') ?></span></th></tr></thead>
                    <tbody>
                        <?php foreach ($result['rows'] as $post): $id = (int) $post['id']; $public = (int) $post['is_public'] === 1; ?>
                            <tr id="admin-post-<?= $id ?>">
                                <td class="is-primary"><a class="admin-post" href="/view_blog?post_id=<?= $id ?>"><span class="member-thumb admin-post__thumb"><?php if ($post['image']): ?><img class="blog-lazy" data-src="<?= e(post_image_url($post['image'], 480)) ?>" alt=""><?php else: ?><i class="bx bx-text"></i><?php endif; ?></span><span><?= e($post['title']) ?></span></a></td>
                                <td class="admin-hide-sm"><span class="admin-user"><?= avatar($post, 'xs') ?><?= e(member_name($post)) ?></span></td>
                                <td class="admin-hide-sm"><?= e(blog_date($post['date'])) ?></td>
                                <td><button type="button" class="ps-badge admin-visibility<?= $public ? ' ps-badge--green' : '' ?>" data-visibility="<?= $id ?>" data-public="<?= $public ? '1' : '0' ?>" title="<?= ps_h('Byt synlighet') ?>"><i class="bx <?= $public ? 'bx-world' : 'bx-lock-alt' ?>"></i><span><?= $public ? ps_t('Offentlig') : ps_t('Inloggade') ?></span></button></td>
                                <td class="admin-hide-md ps-muted ps-small"><i class="bx bx-heart"></i> <?= (int) $post['likes'] ?> <i class="bx bx-comment"></i> <?= (int) $post['comments'] ?></td>
                                <td class="admin-actions"><a class="ps-btn ps-btn--sm ps-btn--ghost ps-btn--icon" href="/upload?edit=<?= $id ?>" aria-label="<?= ps_h('Redigera') ?>" title="<?= ps_h('Redigera') ?>"><i class="bx bx-edit-alt"></i></a><button type="button" class="ps-btn ps-btn--sm ps-btn--ghost ps-btn--icon" data-post-delete="<?= $id ?>" data-remove="admin-post-<?= $id ?>" aria-label="<?= ps_h('Ta bort') ?>" title="<?= ps_h('Ta bort') ?>"><i class="bx bx-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($result['pages'] > 1): ?>
                <nav class="admin-pages" aria-label="<?= ps_h('Sidor') ?>">
                    <?php for ($i = 1; $i <= $result['pages']; $i++): ?><a class="ps-btn ps-btn--sm<?= $i === $page ? ' ps-btn--primary' : ' ps-btn--ghost' ?>" href="<?= e($link(['page' => $i > 1 ? $i : null])) ?>"><?= $i ?></a><?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="ps-empty"><i class="bx bx-filter-alt"></i><div><?= ps_h('Inga inlägg matchade filtret.') ?></div></div>
        <?php endif; ?>
    </div>
</section>
<?php blog_layout_end(['scripts' => '<script src="' . e(blog_asset('assets/blog/admin.js')) . '" defer></script>']);
