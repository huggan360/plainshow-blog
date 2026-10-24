<?php /** @var array $album */ ?>
<a class="album-card<?= (int) $album['published'] > 0 && $album['cover'] ? '' : ' album-card--empty' ?>" href="/view_gallery?gallery_id=<?= (int) $album['gallery_id'] ?>">
    <?php if ((int) $album['published'] > 0 && $album['cover']): ?>
        <img class="blog-lazy" data-src="<?= e(gallery_image_url($album['cover'], 960)) ?>" alt="">
    <?php else: ?>
        <i class="bx bx-image-alt" aria-hidden="true"></i>
    <?php endif; ?>
    <?php if (!(int) $album['is_public']): ?><span class="ps-badge album-card__chip"><i class="bx bx-lock-alt"></i> <?= ps_h('Dolt') ?></span><?php endif; ?>
    <span class="album-card__meta">
        <h3 class="album-card__title"><?= e($album['title']) ?></h3>
        <span class="album-card__count"><?= (int) $album['published'] ?> <?= (int) $album['published'] === 1 ? 'bild' : 'bilder' ?></span>
    </span>
</a>
