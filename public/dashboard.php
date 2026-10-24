<?php
// Mina sidor: the latest from Week of Bali in one place.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Galleries;
use Blog\Domain\Posts;

$identity = require_identity();
$userId = $identity->id();
$conn = wocDb();
$seeHidden = blog_can('blog.gallery.moderate');

$posts = array_slice(array_values(array_filter(Posts::feed($userId, 12), fn ($post) => (int) $post['is_public'] === 1)), 0, 6);
$albums = Galleries::listing(!$seeHidden, 6);
$publicPosts = (int) $conn->query('SELECT COUNT(*) FROM blog_data WHERE is_public = 1')->fetch_row()[0];
$albumCount = (int) $conn->query('SELECT COUNT(*) FROM galleries' . ($seeHidden ? '' : ' WHERE is_public = 1'))->fetch_row()[0];

// Split is its own app with its own database; the blog may read it.
$splitGroups = $splitExpenses = 0;
$expenses = [];
try {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM plainshow_split.members WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $splitGroups = (int) $stmt->get_result()->fetch_row()[0];
    $stmt = $conn->prepare('SELECT COUNT(*) FROM plainshow_split.expenses e JOIN plainshow_split.members m ON m.group_id = e.group_id WHERE m.user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $splitExpenses = (int) $stmt->get_result()->fetch_row()[0];
    $stmt = $conn->prepare("
        SELECT e.id AS expense_id, e.group_id, e.title, e.amount / 100 AS amount, e.spent_on AS expense_date, g.name AS group_name, u.firstname, u.lastname, u.username, u.image_source
        FROM plainshow_split.expenses e
        JOIN plainshow_split.members m ON m.group_id = e.group_id AND m.user_id = ?
        JOIN plainshow_split.`groups` g ON g.id = e.group_id
        LEFT JOIN `user` u ON u.user_id = e.paid_by
        ORDER BY e.created_at DESC, e.id DESC LIMIT 6");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception) {
    // Split is not reachable from here; the overview just leaves it out.
}

$menu = 'dashboard';
blog_layout_start(['title' => ps_t('Mina sidor'), 'active' => 'mina', 'description' => ps_t('Senaste händelser från Week of Bali')]);
?>
<div class="member">
    <?php include dirname(__DIR__) . '/app/views/partials/member-menu.php'; ?>
    <div class="member-main">
        <div class="blog-head ps-page-head">
            <div>
                <h1 class="ps-title blog-head__title"><?= ps_h('Händelser') ?></h1>
                <p class="blog-head__text"><?= ps_h('Det senaste från Week of Bali på ett ställe.') ?></p>
            </div>
        </div>

        <div class="member-stats">
            <a class="member-stat" href="/blogfeed"><span class="member-stat__label"><?= ps_h('Offentliga inlägg') ?><i class="bx bx-news" aria-hidden="true"></i></span><span class="member-stat__value"><?= $publicPosts ?></span></a>
            <a class="member-stat" href="/gallery"><span class="member-stat__label"><?= ps_h('Album för dig') ?><i class="bx bx-photo-album" aria-hidden="true"></i></span><span class="member-stat__value"><?= $albumCount ?></span></a>
            <?php if (ps_url('split') !== ''): ?>
            <a class="member-stat" href="<?= e(ps_url('split')) ?>"><span class="member-stat__label"><?= ps_h('Dina split-grupper') ?><i class="bx bx-wallet-alt" aria-hidden="true"></i></span><span class="member-stat__value"><?= $splitGroups ?></span></a>
            <a class="member-stat" href="<?= e(ps_url('split')) ?>"><span class="member-stat__label"><?= ps_h('Split-utgifter') ?><i class="bx bx-receipt" aria-hidden="true"></i></span><span class="member-stat__value"><?= $splitExpenses ?></span></a>
            <?php endif; ?>
        </div>

        <div class="ps-frames">
            <section class="ps-frame">
                <div class="ps-frame__panel">
                    <div class="ps-frame__head">
                        <h2 class="ps-frame__title"><?= ps_h('Nya inlägg') ?></h2>
                        <a class="ps-btn ps-btn--sm" href="/blogfeed"><?= ps_h('Visa fler') ?></a>
                    </div>
                    <?php if ($posts): ?>
                        <ul class="ps-list member-list ps-frame__body">
                            <?php foreach ($posts as $post): ?>
                                <li class="ps-row">
                                    <?= avatar($post, 'sm') ?>
                                    <div class="ps-row__body">
                                        <div class="ps-row__title"><a href="/view_blog?post_id=<?= (int) $post['id'] ?>"><?= e($post['title']) ?></a></div>
                                        <div class="ps-row__meta"><?= e(member_name($post)) ?>, <?= e(blog_date($post['date'])) ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="ps-muted ps-frame__body"><?= ps_h('Inga nya inlägg just nu.') ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="ps-frame">
                <div class="ps-frame__panel">
                    <div class="ps-frame__head">
                        <h2 class="ps-frame__title"><?= ps_h('Nya album') ?></h2>
                        <a class="ps-btn ps-btn--sm" href="/gallery"><?= ps_h('Visa alla') ?></a>
                    </div>
                    <?php if ($albums): ?>
                        <ul class="ps-list member-list ps-frame__body">
                            <?php foreach ($albums as $album): ?>
                                <li class="ps-row">
                                    <span class="member-thumb"><?php if ($album['cover']): ?><img class="blog-lazy" data-src="<?= e(gallery_image_url($album['cover'], 480)) ?>" alt=""><?php else: ?><i class="bx bx-image-alt"></i><?php endif; ?></span>
                                    <div class="ps-row__body">
                                        <div class="ps-row__title"><a href="/view_gallery?gallery_id=<?= (int) $album['gallery_id'] ?>"><?= e($album['title']) ?></a><?php if (!(int) $album['is_public']): ?><span class="ps-badge"><i class="bx bx-lock-alt"></i><?= ps_h('Dolt') ?></span><?php endif; ?></div>
                                        <div class="ps-row__meta"><?= (int) $album['published'] ?> <?= (int) $album['published'] === 1 ? 'bild' : 'bilder' ?><?= $album['latest'] ? ', ' . e(blog_date($album['latest'])) : '' ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="ps-muted ps-frame__body"><?= ps_h('Inga album ännu.') ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (ps_url('split') !== ''): ?>
            <section class="ps-frame ps-span-all">
                <div class="ps-frame__panel">
                    <div class="ps-frame__head">
                        <h2 class="ps-frame__title"><?= ps_h('Senaste utgifter i Split') ?></h2>
                        <a class="ps-btn ps-btn--sm" href="<?= e(ps_url('split')) ?>"><?= ps_h('Öppna Split') ?></a>
                    </div>
                    <?php if ($expenses): ?>
                        <ul class="ps-list member-list ps-frame__body">
                            <?php foreach ($expenses as $expense): ?>
                                <li class="ps-row">
                                    <?= avatar($expense, 'sm') ?>
                                    <div class="ps-row__body">
                                        <div class="ps-row__title"><?= e($expense['title']) ?></div>
                                        <div class="ps-row__meta"><?= e($expense['group_name']) ?><?= ps_h(', betalt av') ?> <?= e(member_name($expense)) ?>, <?= e(blog_date($expense['expense_date'])) ?></div>
                                    </div>
                                    <span class="member-amount"><?= e(number_format((float) $expense['amount'], 2, ',', ' ')) ?> kr</span>
                                    <a class="ps-btn ps-btn--sm ps-btn--ghost" href="<?= e(ps_url('split', '/group?id=' . (int) $expense['group_id'])) ?>"><?= ps_h('Grupp') ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="ps-muted ps-frame__body"><?= ps_h('Inga utgifter i dina grupper ännu.') ?></p>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php blog_layout_end();
