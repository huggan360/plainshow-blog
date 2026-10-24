<?php
// Home: the trip, the newest albums and posts, and the films.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Galleries;
use Blog\Domain\Posts;
use Blog\Domain\Videos;

$identity = public_identity();
$viewer = $identity?->id() ?? 0;
$albums = Galleries::listing(true, 4);
$posts = array_filter(Posts::feed($viewer, 7), fn ($post) => (int) $post['is_public'] === 1);
$videos = Videos::listing(true);

blog_layout_start(['title' => ps_t('Hem'), 'description' => ps_t('Välkommen till Week of Bali 2026!'), 'active' => 'hem', 'backdrop' => true]);
?>
<section class="blog-hero">
    <p class="ps-eyebrow blog-hero__eyebrow"><?= ps_h('Week of Bali') ?></p>
    <h1 class="blog-hero__title"><?= ps_h('Week of Bali 2026') ?></h1>
    <p class="blog-hero__lead"><?= ps_h('En surfresa i Sri Lanka.') ?></p>
    <div class="ps-inline">
        <a class="ps-btn ps-btn--primary ps-btn--lg" href="/blogfeed"><i class="bx bx-collection"></i><span><?= ps_h('Inlägg') ?></span></a>
        <a class="ps-btn ps-btn--lg" href="/gallery"><i class="bx bx-photo-album"></i><span><?= ps_h('Album') ?></span></a>
        <?php if (!$identity): ?><a class="ps-btn ps-btn--ghost ps-btn--lg" href="/signin"><?= ps_h('Logga in') ?></a><?php endif; ?>
    </div>
</section>

<section class="blog-section">
    <div class="blog-section__head">
        <h2 class="blog-section__title"><?= ps_h('Album') ?></h2>
        <a class="ps-btn ps-btn--sm" href="/gallery"><?= ps_h('Visa alla') ?> <i class="bx bx-right-arrow-alt"></i></a>
    </div>
    <?php if ($albums): ?>
        <div class="album-grid"><?php foreach ($albums as $album) { include dirname(__DIR__) . '/app/views/partials/album-card.php'; } ?></div>
    <?php else: ?>
        <div class="ps-empty"><i class="bx bx-photo-album"></i><div><?= ps_h('Det finns inga album att visa.') ?></div></div>
    <?php endif; ?>
</section>

<section class="blog-section">
    <div class="blog-section__head">
        <h2 class="blog-section__title"><?= ps_h('Senaste inlägg') ?></h2>
        <?php if ($identity): ?><a class="ps-btn ps-btn--sm" href="/blogfeed"><?= ps_h('Alla inlägg') ?> <i class="bx bx-right-arrow-alt"></i></a><?php endif; ?>
    </div>
    <?php if ($posts): ?>
        <div class="post-list"><?php foreach ($posts as $post) { include dirname(__DIR__) . '/app/views/partials/post-card.php'; } ?></div>
    <?php else: ?>
        <div class="ps-empty"><i class="bx bx-collection"></i><div><?= ps_h('Det finns inga inlägg att visa.') ?></div></div>
    <?php endif; ?>
</section>

<?php if ($videos): ?>
<section class="blog-section">
    <div class="blog-section__head"><h2 class="blog-section__title"><?= ps_h('Filmer') ?></h2></div>
    <div class="ps-frames">
        <?php foreach ($videos as $video): ?>
            <article class="ps-frame video-card">
                <div class="ps-frame__panel">
                    <video controls preload="metadata" playsinline src="/assets/<?= e($video['image']) ?>"></video>
                    <div class="video-card__body">
                        <p class="video-card__text"><?= e_lines($video['content']) ?></p>
                        <a class="post-author" href="/profile?user_id=<?= (int) $video['user_id'] ?>"><?= avatar($video, 'sm') ?><span><strong><?= e(member_name($video)) ?></strong><span class="ps-small ps-muted"><?= e(blog_date($video['date'])) ?></span></span></a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php blog_layout_end();
