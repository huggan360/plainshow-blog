<?php
// Search people, posts and albums.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Search;

$identity = require_identity();
$query = trim((string) ($_GET['query'] ?? ''));
$results = Search::run($query, $identity->id(), blog_can('blog.gallery.moderate'));
$total = count($results['people']) + count($results['posts']) + count($results['albums']);

/** The query, highlighted in escaped text. */
$mark = function (string $text) use ($query): string {
    if ($query === '') {
        return e($text);
    }
    $parts = preg_split('/(' . preg_quote($query, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    $html = '';
    foreach ($parts as $index => $part) {
        $html .= $index % 2 ? '<mark>' . e($part) . '</mark>' : e($part);
    }
    return $html;
};

blog_layout_start(['title' => $query !== '' ? ps_t('Sök: ') . $query : 'Sök', 'active' => '']);
?>
<div class="blog-head ps-page-head">
    <div>
        <h1 class="ps-title blog-head__title"><?= ps_h('Sök') ?></h1>
        <p class="blog-head__text"><?= $query === '' ? ps_t('Sök bland inlägg, album och personer.') : ($total ? $total . ' träffar för ”' . e($query) . '”' : 'Inga träffar för ”' . e($query) . '”') ?></p>
    </div>
</div>
<form class="search-page__form" action="/search" method="get" role="search">
    <i class="bx bx-search" aria-hidden="true"></i>
    <input class="ps-input" type="search" name="query" value="<?= e($query) ?>" placeholder="<?= ps_h('Sök inlägg, album och personer') ?>" aria-label="<?= ps_h('Sök') ?>" autofocus>
    <button type="submit" class="ps-btn ps-btn--primary"><?= ps_h('Sök') ?></button>
</form>

<?php if ($results['people']): ?>
<section class="blog-section">
    <div class="blog-section__head"><h2 class="blog-section__title"><?= ps_h('Personer') ?></h2></div>
    <div class="search-people">
        <?php foreach ($results['people'] as $person): ?>
            <a class="search-person" href="/profile?user_id=<?= (int) $person['user_id'] ?>">
                <?= avatar($person, 'lg') ?>
                <strong><?= $mark(member_name($person)) ?></strong>
                <span class="ps-muted ps-small">@<?= e($person['username']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($results['albums']): ?>
<section class="blog-section">
    <div class="blog-section__head"><h2 class="blog-section__title"><?= ps_h('Album') ?></h2></div>
    <div class="album-grid"><?php foreach ($results['albums'] as $album) { include dirname(__DIR__) . '/app/views/partials/album-card.php'; } ?></div>
</section>
<?php endif; ?>

<?php if ($results['posts']): ?>
<section class="blog-section">
    <div class="blog-section__head"><h2 class="blog-section__title"><?= ps_h('Inlägg') ?></h2></div>
    <section class="ps-frame">
        <div class="ps-frame__panel search-posts">
            <ul class="ps-list member-list">
                <?php foreach ($results['posts'] as $post): ?>
                    <li class="ps-row">
                        <a class="member-thumb" href="/view_blog?post_id=<?= (int) $post['id'] ?>" tabindex="-1" aria-hidden="true"><?php if ($post['image']): ?><img class="blog-lazy" data-src="<?= e(post_image_url($post['image'], 480)) ?>" alt=""><?php else: ?><i class="bx bx-text"></i><?php endif; ?></a>
                        <div class="ps-row__body">
                            <div class="ps-row__title"><a href="/view_blog?post_id=<?= (int) $post['id'] ?>"><?= $mark((string) $post['title']) ?></a></div>
                            <div class="search-posts__excerpt"><?= $mark(excerpt($post['content'], 160)) ?></div>
                            <div class="ps-row__meta"><?= e(member_name($post)) ?>, <?= e(blog_date($post['date'])) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
</section>
<?php endif; ?>

<?php if ($query !== '' && !$total): ?>
    <div class="ps-empty"><i class="bx bx-search-alt"></i><div><?= ps_h('Prova ett annat ord.') ?></div></div>
<?php endif; ?>
<?php blog_layout_end();
