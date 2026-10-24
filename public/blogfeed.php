<?php
// The feed: stories across the top, then every post, newest first.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Posts;
use Blog\Domain\Stories;

$identity = require_identity();
$member = current_member();
$stories = Stories::bar($identity->id());
$hasOwn = $stories && (int) $stories[0]['user_id'] === $identity->id();
$posts = Posts::feed($identity->id(), 11);
$more = count($posts) > 10;

blog_layout_start(['title' => ps_t('Inlägg'), 'active' => 'inlagg', 'backdrop' => true]);
?>
<div class="blog-head ps-page-head ps-page-head--gradient">
    <div>
        <h1 class="ps-title blog-head__title"><?= ps_h('Inlägg') ?></h1>
        <p class="blog-head__text"><?= ps_h('Det senaste från resan.') ?></p>
    </div>
</div>

<section class="ps-frame ps-frame--compact feed-stories" aria-label="<?= ps_h('Stories') ?>">
    <div class="ps-frame__panel">
        <div class="story-bar">
            <?php if (blog_can('blog.story.create')): ?>
                <button type="button" class="story<?= $hasOwn ? '' : ' is-empty' ?>" data-story-add <?= $hasOwn ? 'data-story-user="' . $identity->id() . '"' : '' ?>>
                    <span class="story__ring"><?= avatar($member) ?></span>
                    <span class="story__add" aria-hidden="true"><i class="bx bx-plus"></i></span>
                    <span class="story__name"><?= $hasOwn ? ps_t('Din story') : ps_t('Lägg till') ?></span>
                </button>
            <?php endif; ?>
            <?php foreach ($stories as $person): if ((int) $person['user_id'] === $identity->id()) { continue; } ?>
                <button type="button" class="story<?= (int) $person['seen'] >= (int) $person['active'] ? ' is-viewed' : '' ?>" data-story-user="<?= (int) $person['user_id'] ?>" data-story-name="<?= e(member_name($person)) ?>">
                    <span class="story__ring"><?= avatar($person) ?></span>
                    <span class="story__name"><?= e($person['firstname'] ?: $person['username']) ?></span>
                </button>
            <?php endforeach; ?>
            <?php if (!$stories): ?><p class="ps-muted ps-small feed-stories__empty"><?= ps_h('Inga stories just nu.') ?></p><?php endif; ?>
        </div>
    </div>
</section>

<div class="post-list feed-posts" id="feed">
    <?php foreach (array_slice($posts, 0, 10) as $post) { include dirname(__DIR__) . '/app/views/partials/post-card.php'; } ?>
</div>
<?php if (!$posts): ?><div class="ps-empty"><i class="bx bx-collection"></i><div><?= ps_h('Inga inlägg ännu.') ?></div></div><?php endif; ?>
<?php if ($more): ?>
    <div class="feed-more"><button type="button" class="ps-btn ps-btn--lg" id="feed-more" data-offset="10"><?= ps_h('Visa fler') ?></button></div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/app/views/partials/story-viewer.php'; ?>
<?php blog_layout_end(['scripts' => '<script src="' . e(blog_asset('assets/blog/stories.js')) . '" defer></script>']);
