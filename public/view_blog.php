<?php
// One post: the text and pictures, likes and comments.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Comments;
use Blog\Domain\Posts;

$postId = (int) ($_GET['post_id'] ?? 0);
$identity = public_identity();
$post = $postId > 0 ? Posts::find($postId, $identity?->id() ?? 0) : null;
if ($post && (int) $post['is_public'] !== 1 && $identity === null) {
    $identity = require_identity();
    $post = Posts::find($postId, $identity->id());
}
if (!$post) {
    http_response_code(404);
    blog_layout_start(['title' => ps_t('Inlägget finns inte'), 'active' => 'inlagg']);
    echo '<div class="ps-empty blog-missing"><i class="bx bx-ghost"></i><h1 class="ps-heading">' . ps_h('Inlägget finns inte') . '</h1><p>' . ps_h('Det kan ha tagits bort.') . '</p><a class="ps-btn" href="/blogfeed"><i class="bx bx-left-arrow-alt"></i>' . ps_h('Till inläggen') . '</a></div>';
    blog_layout_end();
    exit;
}

$blocks = Posts::blocks($postId);
if (!$blocks) {
    // Posts from before the block editor: one text and one picture.
    if (trim((string) $post['content']) !== '') {
        $blocks[] = ['type' => 'text', 'content' => (string) $post['content']];
    }
    if (!empty($post['image'])) {
        $blocks[] = ['type' => 'image', 'path' => (string) $post['image']];
    }
}
// Pictures that follow each other share one grid.
$groups = [];
foreach ($blocks as $block) {
    $last = array_key_last($groups);
    if ($block['type'] === 'image' && $last !== null && $groups[$last]['type'] === 'images') {
        $groups[$last]['paths'][] = $block['path'];
    } elseif ($block['type'] === 'image') {
        $groups[] = ['type' => 'images', 'paths' => [$block['path']]];
    } else {
        $groups[] = $block;
    }
}

$owner = (int) $post['user'];
$canEdit = Posts::canEdit($identity, $post);
$likers = $identity ? Posts::likers($postId) : [];
$comments = Comments::forPost($postId);
$guestCanComment = $identity === null && (int) $post['is_public'] === 1;

blog_layout_start([
    'title' => (string) $post['title'],
    'description' => excerpt($post['content'], 150),
    'active' => 'inlagg',
    'body' => 'blog-post-page',
    'narrow' => true,
]);
?>
<article class="post" data-post="<?= $postId ?>">
    <a class="blog-back" href="/blogfeed"><i class="bx bx-left-arrow-alt" aria-hidden="true"></i><?= ps_h('Inlägg') ?></a>

    <header class="post__head">
        <div class="ps-inline post__meta-top">
            <span class="ps-eyebrow ps-eyebrow--accent"><?= e(blog_date($post['date'])) ?></span>
            <?php if ((int) $post['is_public'] !== 1): ?><span class="ps-badge"><i class="bx bx-lock-alt" aria-hidden="true"></i><?= ps_h('Bara inloggade') ?></span><?php endif; ?>
        </div>
        <h1 class="post__title"><?= e($post['title']) ?></h1>
        <div class="post__byline">
            <a class="post-author" href="/profile?user_id=<?= $owner ?>"><?= avatar($post) ?><span><strong><?= e(member_name($post)) ?></strong><span class="ps-small ps-muted"><?= e(blog_ago($post['date'])) ?></span></span></a>
            <?php if ($canEdit): ?>
                <div class="ps-inline post__tools">
                    <a class="ps-btn ps-btn--sm" href="/upload?edit=<?= $postId ?>"><i class="bx bx-edit-alt"></i><span><?= ps_h('Redigera') ?></span></a>
                    <button type="button" class="ps-btn ps-btn--sm ps-btn--danger" data-post-delete="<?= $postId ?>"><i class="bx bx-trash"></i><span><?= ps_h('Ta bort') ?></span></button>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="post__body">
        <?php foreach ($groups as $index => $group): ?>
            <?php if ($group['type'] === 'text'): ?>
                <div class="ps-reading post__text"><?= e_links($group['content']) ?></div>
            <?php else: $count = count($group['paths']); ?>
                <div class="post__images post__images--<?= min($count, 3) ?>">
                    <?php foreach ($group['paths'] as $path): ?>
                        <a class="post__image" href="<?= e(post_image_url($path)) ?>" data-lightbox="post">
                            <img class="blog-lazy" data-src="<?= e(post_image_url($path, $count === 1 ? 1600 : 960)) ?>" alt="">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$groups): ?><p class="ps-muted"><?= ps_h('Inlägget är tomt.') ?></p><?php endif; ?>
    </div>

    <div class="post__bar">
        <?php if ($identity): ?>
            <button type="button" class="ps-btn like-btn<?= $post['liked'] ? ' is-liked' : '' ?>" data-like="<?= $postId ?>" data-liked="<?= $post['liked'] ? '1' : '0' ?>" aria-pressed="<?= $post['liked'] ? 'true' : 'false' ?>">
                <i class="bx <?= $post['liked'] ? 'bxs-heart' : 'bx-heart' ?>"></i><span data-like-count><?= (int) $post['likes'] ?></span><span class="ps-sr-only">gillningar</span>
            </button>
            <?php if ($likers): ?>
                <button type="button" class="post__likers" data-modal-open="likers-modal">
                    <span class="post__likers-faces"><?php foreach (array_slice($likers, 0, 3) as $person) { echo avatar($person, 'xs'); } ?></span>
                    <span><?= ps_h('Gillas av') ?> <strong><?= e(member_name($likers[0])) ?></strong><?= count($likers) > 1 ? ' och ' . (count($likers) - 1) . ' till' : '' ?></span>
                </button>
            <?php endif; ?>
        <?php else: ?>
            <span class="ps-inline ps-muted"><i class="bx bx-heart" aria-hidden="true"></i><?= (int) $post['likes'] ?> gillningar</span>
        <?php endif; ?>
        <a class="ps-btn ps-btn--ghost post__to-comments" href="#kommentarer"><i class="bx bx-comment"></i><span data-comment-count><?= count($comments) ?></span><span class="ps-sr-only">kommentarer</span></a>
    </div>
</article>

<section class="ps-frame post-comments" id="kommentarer" aria-labelledby="comments-title">
    <div class="ps-frame__panel">
        <h2 class="ps-frame__title" id="comments-title"><?= ps_h('Kommentarer') ?></h2>
        <ul class="comment-list" data-comment-list>
            <?php foreach ($comments as $comment) {
                $canDelete = Comments::canDelete($identity, $comment, $owner);
                include dirname(__DIR__) . '/app/views/partials/comment.php';
            } ?>
        </ul>
        <p class="ps-muted comment-list__empty" data-comment-empty<?= $comments ? ' hidden' : '' ?>><?= ps_h('Inga kommentarer ännu.') ?></p>

        <?php if ($identity && blog_can('blog.comment.create')): ?>
            <form class="comment-form" data-comment-form data-endpoint="posts/<?= $postId ?>/comments">
                <?= avatar(current_member(), 'sm') ?>
                <div class="comment-form__field">
                    <label class="ps-sr-only" for="comment-text"><?= ps_h('Skriv en kommentar') ?></label>
                    <textarea class="ps-textarea" id="comment-text" name="comment" rows="1" maxlength="4000" placeholder="<?= ps_h('Skriv en kommentar...') ?>" required data-autogrow></textarea>
                    <button type="submit" class="ps-btn ps-btn--primary"><i class="bx bx-send"></i><span><?= ps_h('Skicka') ?></span></button>
                </div>
            </form>
        <?php elseif ($guestCanComment): ?>
            <form class="comment-form comment-form--guest" data-comment-form data-endpoint="posts/<?= $postId ?>/guest-comments">
                <div class="comment-form__trap" aria-hidden="true"><label><?= ps_h('Webbplats') ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <label class="ps-field">
                    <span class="ps-label"><?= ps_h('Namn') ?></span>
                    <input class="ps-input" type="text" name="name" maxlength="60" autocomplete="name" placeholder="<?= ps_h('Ditt namn här...') ?>" required>
                </label>
                <div class="comment-form__field">
                    <label class="ps-sr-only" for="comment-text"><?= ps_h('Skriv en kommentar') ?></label>
                    <textarea class="ps-textarea" id="comment-text" name="comment" rows="1" maxlength="2000" placeholder="<?= ps_h('Skriv en kommentar...') ?>" required data-autogrow></textarea>
                    <button type="submit" class="ps-btn ps-btn--primary"><i class="bx bx-send"></i><span><?= ps_h('Skicka') ?></span></button>
                </div>
                <p class="ps-help"><?= ps_h('Du kommenterar som gäst.') ?> <a href="/signin"><?= ps_h('Logga in') ?></a> <?= ps_h('för att kommentera med ditt konto.') ?></p>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($likers): ?>
<div class="ps-modal-backdrop" id="likers-modal" hidden>
    <div class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="likers-title">
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="likers-title"><?= ps_h('Gillas av') ?></h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button></div>
            <ul class="ps-list ps-modal__body likers">
                <?php foreach ($likers as $person): ?>
                    <li class="ps-row"><a class="post-author" href="/profile?user_id=<?= (int) $person['user_id'] ?>"><?= avatar($person, 'sm') ?><strong><?= e(member_name($person)) ?></strong></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>
<?php blog_layout_end(['scripts' => '<script src="' . e(blog_asset('assets/blog/post.js')) . '" defer></script>']);
