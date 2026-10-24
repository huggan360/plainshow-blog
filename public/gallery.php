<?php
// Album: every album with photos. Hidden albums only for people who manage albums.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Galleries;

$identity = public_identity();
$manage = $identity !== null && blog_can('blog.gallery.moderate');
$albums = array_values(array_filter(Galleries::listing(!$manage), fn ($album) => $manage || (int) $album['published'] > 0));
$photos = array_sum(array_map(fn ($album) => (int) $album['published'], $albums));

blog_layout_start(['title' => ps_t('Album'), 'description' => ps_t('Week of Bali, album'), 'active' => 'album', 'backdrop' => true]);
?>
<div class="blog-head ps-page-head">
    <div>
        <h1 class="ps-title blog-head__title"><?= ps_h('Album') ?></h1>
        <p class="blog-head__text"><?= count($albums) ?> <?= ps_h('album,') ?> <?= $photos ?> bilder.</p>
    </div>
    <?php if ($manage): ?><button type="button" class="ps-btn ps-btn--primary" data-modal-open="album-new"><i class="bx bx-plus"></i><span><?= ps_h('Nytt album') ?></span></button><?php endif; ?>
</div>
<?php if ($albums): ?>
    <div class="album-grid"><?php foreach ($albums as $album) { include dirname(__DIR__) . '/app/views/partials/album-card.php'; } ?></div>
<?php else: ?>
    <div class="ps-empty"><i class="bx bx-photo-album"></i><div><?= ps_h('Det finns inga album.') ?></div></div>
<?php endif; ?>

<?php if ($manage): ?>
<div class="ps-modal-backdrop" id="album-new" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="album-new-title" data-album-create data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="album-new-title"><?= ps_h('Nytt album') ?></h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body">
                <label class="ps-field"><span class="ps-label"><?= ps_h('Namn') ?></span><input class="ps-input" name="title" maxlength="190" required autocomplete="off" placeholder="<?= ps_h('Till exempel V5') ?>"></label>
                <label class="ps-check"><input type="checkbox" name="is_public" value="1" checked><span><?= ps_h('Synligt för alla') ?></span></label>
                <p class="ps-help"><?= ps_h('Nya bilder syns först när du publicerar dem.') ?></p>
            </div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close><?= ps_h('Avbryt') ?></button><button type="submit" class="ps-btn ps-btn--primary"><?= ps_h('Skapa album') ?></button></div>
        </div>
    </form>
</div>
<script src="<?= e(blog_asset('assets/blog/albums.js')) ?>" defer></script>
<?php endif; ?>
<?php blog_layout_end();
