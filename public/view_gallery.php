<?php
// One album: its photos in a grid with a lightbox. People who manage albums
// also publish, hide and remove photos here, and anyone allowed can upload.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Albums;
use Blog\Domain\Galleries;

$identity = public_identity();
$manage = $identity !== null && blog_can('blog.gallery.moderate');
$canUpload = $identity !== null && blog_can('blog.gallery.create');
$albumId = (int) ($_GET['gallery_id'] ?? 0);
$album = Galleries::find($albumId);
if (!$album || (!(int) $album['is_public'] && !$manage)) {
    http_response_code(404);
    blog_layout_start(['title' => ps_t('Albumet finns inte'), 'active' => 'album']);
    echo '<div class="ps-empty blog-missing"><i class="bx bx-photo-album"></i><h1 class="ps-heading">' . ps_h('Albumet finns inte') . '</h1><a class="ps-btn" href="/gallery"><i class="bx bx-left-arrow-alt"></i>' . ps_h('Till albumen') . '</a></div>';
    blog_layout_end();
    exit;
}
$me = $identity?->id() ?? 0;
$photos = array_values(array_filter(
    Galleries::photos($albumId, $manage || $canUpload),
    fn ($photo) => (int) $photo['is_published'] === 1 || $manage || (int) $photo['upload_id'] === $me,
));
$published = count(array_filter($photos, fn ($photo) => (int) $photo['is_published'] === 1));
$cover = null;
foreach ($photos as $photo) {
    if ((int) $photo['is_published'] === 1) {
        $cover = $photo['image_source'];
        break;
    }
}
$selectable = $manage || $canUpload;

blog_layout_start([
    'title' => (string) $album['title'],
    'description' => ps_t('Album: ') . $album['title'],
    'active' => 'album',
    'body' => 'blog-album-page',
]);
?>
<a class="blog-back" href="/gallery"><i class="bx bx-left-arrow-alt" aria-hidden="true"></i><?= ps_h('Album') ?></a>
<header class="ps-hero album-hero"<?= $cover ? ' style="background-image: url(\'' . e(gallery_image_url($cover, 1600)) . '\')"' : '' ?>>
    <div class="album-hero__body">
        <div class="ps-inline album-hero__chips">
            <?php if (!(int) $album['is_public']): ?><span class="ps-badge"><i class="bx bx-lock-alt"></i><?= ps_h('Dolt album') ?></span><?php endif; ?>
            <span class="ps-badge"><?= $published ?> <?= $published === 1 ? 'bild' : 'bilder' ?><?= $manage && count($photos) > $published ? ', ' . (count($photos) - $published) . ' väntar' : '' ?></span>
        </div>
        <h1 class="album-hero__title" data-album-title><?= e($album['title']) ?></h1>
    </div>
    <?php if ($selectable): ?>
        <div class="ps-inline album-hero__tools">
            <?php if ($canUpload): ?><button type="button" class="ps-btn ps-btn--primary" data-upload-open><i class="bx bx-upload"></i><span><?= ps_h('Ladda upp') ?></span></button><?php endif; ?>
            <?php if ($photos): ?><button type="button" class="ps-btn" data-select-toggle><i class="bx bx-check-square"></i><span><?= ps_h('Välj') ?></span></button><?php endif; ?>
            <?php if ($manage): ?>
                <div class="ps-dropdown" data-ps-dropdown>
                    <button type="button" class="ps-btn ps-btn--icon" data-ps-dropdown-trigger aria-label="<?= ps_h('Mer') ?>" aria-expanded="false"><i class="bx bx-dots-horizontal-rounded"></i></button>
                    <div class="ps-dropdown__menu" data-ps-dropdown-menu hidden>
                        <button type="button" class="ps-dropdown__item" data-modal-open="album-edit"><i class="bx bx-edit-alt"></i><?= ps_h('Byt namn') ?></button>
                        <button type="button" class="ps-dropdown__item" data-album-visibility="<?= (int) $album['is_public'] ? '0' : '1' ?>"><i class="bx <?= (int) $album['is_public'] ? 'bx-hide' : 'bx-show' ?>"></i><?= (int) $album['is_public'] ? ps_t('Dölj albumet') : ps_t('Visa albumet för alla') ?></button>
                        <div class="ps-dropdown__sep"></div>
                        <button type="button" class="ps-dropdown__item album-danger" data-album-delete><i class="bx bx-trash"></i><?= ps_h('Ta bort albumet') ?></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</header>

<?php if ($canUpload): ?>
<section class="ps-frame uploader" data-uploader hidden>
    <div class="ps-frame__panel">
        <div class="ps-frame__head">
            <div>
                <h2 class="ps-frame__title"><?= ps_h('Ladda upp bilder') ?></h2>
                <p class="ps-frame__text"><?= $manage ? ps_t('Bilderna sparas som opublicerade så att du kan välja vilka som ska synas.') : ps_t('Bilderna syns när någon som sköter albumen har publicerat dem.') ?></p>
            </div>
            <button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-upload-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button>
        </div>
        <label class="uploader__drop" data-upload-drop>
            <input type="file" accept="image/*" multiple data-upload-input>
            <i class="bx bx-images" aria-hidden="true"></i>
            <strong><?= ps_h('Dra in bilder hit eller klicka för att välja') ?></strong>
            <span class="ps-muted ps-small"><?= ps_h('JPEG, PNG, GIF, WebP eller HEIC. Stora bilder görs mindre innan de skickas.') ?></span>
        </label>
        <?php if ($manage): ?><label class="ps-check uploader__publish"><input type="checkbox" data-upload-publish><span><?= ps_h('Publicera bilderna direkt') ?></span></label><?php endif; ?>
        <div class="uploader__summary" data-upload-summary hidden>
            <div class="ps-between"><span data-upload-status></span><span class="ps-muted ps-small" data-upload-speed></span></div>
            <div class="ps-meter"><span data-upload-total style="width: 0%"></span></div>
        </div>
        <ul class="uploader__list" data-upload-list></ul>
    </div>
</section>
<?php endif; ?>

<div class="album-select" data-select-bar hidden>
    <span data-select-count><?= ps_h('0 valda') ?></span>
    <button type="button" class="ps-btn ps-btn--sm ps-btn--ghost" data-select-all><?= ps_h('Välj alla') ?></button>
    <span class="album-select__spacer"></span>
    <?php if ($manage): ?>
        <button type="button" class="ps-btn ps-btn--sm" data-select-action="publish"><i class="bx bx-show"></i><span><?= ps_h('Publicera') ?></span></button>
        <button type="button" class="ps-btn ps-btn--sm" data-select-action="hide"><i class="bx bx-hide"></i><span><?= ps_h('Dölj') ?></span></button>
    <?php endif; ?>
    <button type="button" class="ps-btn ps-btn--sm ps-btn--danger" data-select-action="delete"><i class="bx bx-trash"></i><span><?= ps_h('Ta bort') ?></span></button>
    <button type="button" class="ps-btn ps-btn--sm ps-btn--primary" data-select-toggle><?= ps_h('Klar') ?></button>
</div>

<div class="photo-grid" data-photo-grid data-album="<?= $albumId ?>"<?= $photos ? '' : ' hidden' ?>>
    <?php foreach ($photos as $photo): $id = (int) $photo['image_id']; $live = (int) $photo['is_published'] === 1; ?>
        <figure class="photo<?= $live ? '' : ' is-draft' ?>" data-photo="<?= $id ?>" data-own="<?= (int) $photo['upload_id'] === $me ? '1' : '0' ?>">
            <a class="photo__link" href="<?= e(gallery_image_url($photo['image_source'])) ?>" data-lightbox="album">
                <img class="blog-lazy" data-src="<?= e(gallery_image_url($photo['image_source'], 960)) ?>" alt="">
            </a>
            <?php if (!$live): ?><span class="ps-badge photo__badge"><?= ps_h('Inte publicerad') ?></span><?php endif; ?>
            <?php if ($selectable): ?><label class="photo__check"><input type="checkbox" value="<?= $id ?>" aria-label="<?= ps_h('Välj bilden') ?>"><span></span></label><?php endif; ?>
        </figure>
    <?php endforeach; ?>
</div>
<div class="ps-empty" data-photo-empty<?= $photos ? ' hidden' : '' ?>><i class="bx bx-image-alt"></i><div><?= ps_h('Det finns inga bilder i detta album.') ?></div><?php if ($canUpload): ?><button type="button" class="ps-btn ps-btn--primary" data-upload-open><i class="bx bx-upload"></i><?= ps_h('Ladda upp bilder') ?></button><?php endif; ?></div>

<?php if ($manage): ?>
<div class="ps-modal-backdrop" id="album-edit" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="album-edit-title" data-album-rename data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="album-edit-title"><?= ps_h('Byt namn') ?></h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body"><label class="ps-field"><span class="ps-label"><?= ps_h('Namn') ?></span><input class="ps-input" name="title" maxlength="190" required value="<?= e($album['title']) ?>"></label></div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close><?= ps_h('Avbryt') ?></button><button type="submit" class="ps-btn ps-btn--primary"><?= ps_h('Spara') ?></button></div>
        </div>
    </form>
</div>
<?php endif; ?>
<script type="application/json" id="album-data"><?= json_encode(['album' => $albumId, 'manage' => $manage, 'chunk' => Albums::CHUNK_BYTES, 'openUpload' => isset($_GET['upload'])], JSON_HEX_TAG) ?></script>
<?php blog_layout_end(['scripts' => $selectable ? '<script src="' . e(blog_asset('assets/blog/albums.js')) . '" defer></script>' : '']);
