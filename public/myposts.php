<?php
// Mina inlägg: everything you have written, with edit and delete.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Posts;

$identity = require_identity();
$posts = Posts::byAuthor($identity->id(), $identity->id());

$menu = 'myposts';
blog_layout_start(['title' => ps_t('Mina inlägg'), 'active' => 'mina']);
?>
<div class="member">
    <?php include dirname(__DIR__) . '/app/views/partials/member-menu.php'; ?>
    <div class="member-main">
        <div class="blog-head ps-page-head">
            <div>
                <h1 class="ps-title blog-head__title"><?= ps_h('Mina inlägg') ?></h1>
                <p class="blog-head__text"><?= count($posts) ?> <?= count($posts) === 1 ? 'inlägg' : 'inlägg' ?><?= ps_h('. Redigera ordning på text och bilder, uppdatera eller radera inlägg.') ?></p>
            </div>
            <?php if (blog_can('blog.post.create')): ?><a class="ps-btn ps-btn--primary" href="/upload"><i class="bx bx-plus"></i><span><?= ps_h('Nytt inlägg') ?></span></a><?php endif; ?>
        </div>
        <?php if ($posts): ?>
            <section class="ps-frame">
                <div class="ps-frame__panel myposts">
                    <ul class="ps-list member-list">
                        <?php foreach ($posts as $post): $id = (int) $post['id']; ?>
                            <li class="ps-row" id="post-<?= $id ?>">
                                <a class="member-thumb" href="/view_blog?post_id=<?= $id ?>" tabindex="-1" aria-hidden="true"><?php if ($post['image']): ?><img class="blog-lazy" data-src="<?= e(post_image_url($post['image'], 480)) ?>" alt=""><?php else: ?><i class="bx bx-text"></i><?php endif; ?></a>
                                <div class="ps-row__body">
                                    <div class="ps-row__title"><a href="/view_blog?post_id=<?= $id ?>"><?= e($post['title']) ?></a></div>
                                    <div class="ps-row__meta myposts__meta">
                                        <span><?= e(blog_date($post['date'])) ?></span>
                                        <?= (int) $post['is_public'] === 1 ? '<span class="ps-badge ps-badge--green">' . ps_h('Offentlig') . '</span>' : '<span class="ps-badge"><i class="bx bx-lock-alt"></i>' . ps_h('Privat') . '</span>' ?>
                                        <span><i class="bx bx-heart" aria-hidden="true"></i> <?= (int) $post['likes'] ?></span>
                                        <span><i class="bx bx-comment" aria-hidden="true"></i> <?= (int) $post['comments'] ?></span>
                                    </div>
                                </div>
                                <div class="ps-row__action">
                                    <a class="ps-btn ps-btn--sm" href="/upload?edit=<?= $id ?>"><i class="bx bx-edit-alt"></i><span class="myposts__label"><?= ps_h('Redigera') ?></span></a>
                                    <button type="button" class="ps-btn ps-btn--sm ps-btn--ghost ps-btn--icon" data-post-delete="<?= $id ?>" data-remove="post-<?= $id ?>" aria-label="Ta bort <?= e($post['title']) ?>" title="<?= ps_h('Ta bort') ?>"><i class="bx bx-trash"></i></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php else: ?>
            <div class="ps-empty"><i class="bx bx-edit"></i><div><?= ps_h('Du har inga inlägg ännu.') ?></div><?php if (blog_can('blog.post.create')): ?><a class="ps-btn ps-btn--primary" href="/upload"><i class="bx bx-plus"></i><?= ps_h('Nytt inlägg') ?></a><?php endif; ?></div>
        <?php endif; ?>
    </div>
</div>
<?php blog_layout_end();
