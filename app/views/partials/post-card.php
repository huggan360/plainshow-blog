<?php /** @var array $post */ ?>
<article class="ps-frame post-card<?= empty($post['image']) ? ' post-card--text' : '' ?>">
    <div class="ps-frame__panel">
        <?php if (!empty($post['image'])): ?>
        <a class="post-card__media" href="/view_blog?post_id=<?= (int) $post['id'] ?>" tabindex="-1" aria-hidden="true">
            <img class="blog-lazy" data-src="<?= e(post_image_url($post['image'], 960)) ?>" alt="">
            <span class="ps-badge post-card__date"><?= e(blog_date($post['date'])) ?></span>
        </a>
        <?php endif; ?>
        <div class="post-card__body">
            <?php if (empty($post['image'])): ?><span class="ps-eyebrow"><?= e(blog_date($post['date'])) ?></span><?php endif; ?>
            <h2 class="post-card__title"><a href="/view_blog?post_id=<?= (int) $post['id'] ?>"><?= e($post['title']) ?></a></h2>
            <p class="post-card__excerpt"><?= e(excerpt($post['content'], 180)) ?></p>
            <div class="post-card__foot">
                <a class="post-author" href="/profile?user_id=<?= (int) $post['user'] ?>"><?= avatar($post, 'sm') ?><span><strong><?= e(member_name($post)) ?></strong></span></a>
                <div class="ps-inline">
                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--sm like-btn<?= $post['liked'] ? ' is-liked' : '' ?>" data-like="<?= (int) $post['id'] ?>" data-liked="<?= $post['liked'] ? '1' : '0' ?>" aria-pressed="<?= $post['liked'] ? 'true' : 'false' ?>" aria-label="<?= ps_h('Gilla') ?>">
                        <i class="bx <?= $post['liked'] ? 'bxs-heart' : 'bx-heart' ?>"></i><span data-like-count><?= (int) $post['likes'] ?></span>
                    </button>
                    <a class="ps-btn ps-btn--ghost ps-btn--sm" href="/view_blog?post_id=<?= (int) $post['id'] ?>#kommentarer" aria-label="<?= ps_h('Kommentarer') ?>"><i class="bx bx-comment"></i><span><?= (int) $post['comments'] ?></span></a>
                    <?php if (empty($post['is_public'])): ?><span class="ps-badge" title="<?= ps_h('Bara inloggade') ?>"><i class="bx bx-lock-alt"></i></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</article>
