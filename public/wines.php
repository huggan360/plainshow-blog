<?php
// Vinrating: the wines from the trips, with one shared rating each.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Wines;

$identity = require_identity();
$search = trim((string) ($_GET['search'] ?? ''));
$wines = Wines::all($search);
$canRate = Wines::canRate($identity);
$canManage = Wines::canManage($identity);
$rated = array_filter($wines, fn ($wine) => $wine['rating'] !== null);
$average = $rated ? array_sum(array_column($rated, 'rating')) / count($rated) : null;

blog_layout_start(['title' => ps_t('Vinrating'), 'active' => '']);
?>
<div class="blog-head ps-page-head">
    <div>
        <h1 class="ps-title blog-head__title"><?= ps_h('Vinrating') ?></h1>
        <p class="blog-head__text"><?= count($wines) ?> <?= ps_h('viner,') ?> <?= count($rated) ?> <?= ps_h('bedömda') ?><?= $average !== null ? ', snittbetyg ' . e(number_format($average, 1, ',', '')) : '' ?>.</p>
    </div>
    <div class="ps-inline">
        <form class="wine-search" action="/wines" method="get" role="search">
            <i class="bx bx-search" aria-hidden="true"></i>
            <input class="ps-input" type="search" name="search" value="<?= e($search) ?>" placeholder="<?= ps_h('Sök') ?>" aria-label="<?= ps_h('Sök viner') ?>">
        </form>
        <?php if ($canManage): ?><button type="button" class="ps-btn ps-btn--primary" data-modal-open="wine-new"><i class="bx bx-plus"></i><span><?= ps_h('Lägg till nytt vin') ?></span></button><?php endif; ?>
    </div>
</div>

<?php if ($wines): ?>
<div class="wine-grid">
    <?php foreach ($wines as $wine): $id = (int) $wine['wine_id']; ?>
        <article class="ps-frame wine" id="wine-<?= $id ?>">
            <div class="ps-frame__panel">
                <div class="wine__media">
                    <?php if ($wine['image']): ?><img class="blog-lazy" data-src="<?= e(post_image_url($wine['image'], 480)) ?>" alt=""><?php else: ?><i class="bx bx-wine" aria-hidden="true"></i><?php endif; ?>
                    <?php if ($wine['rating'] !== null): ?><span class="wine__score"><?= e(rtrim(rtrim(number_format((float) $wine['rating'], 1, ',', ''), '0'), ',')) ?><small>/10</small></span><?php endif; ?>
                </div>
                <div class="wine__body">
                    <h2 class="wine__title" data-wine-title><?= e($wine['title']) ?></h2>
                    <p class="wine__text" data-wine-text><?= e_lines($wine['description']) ?></p>
                    <?php if ($wine['rating'] !== null): ?>
                        <?php if (trim((string) $wine['comment']) !== ''): ?><blockquote class="wine__comment"><?= e_lines($wine['comment']) ?></blockquote><?php endif; ?>
                        <?php if ($wine['username']): ?><p class="ps-small ps-muted wine__by"><?= ps_h('Betyg av') ?> <?= e(member_name($wine)) ?></p><?php endif; ?>
                    <?php else: ?>
                        <p class="ps-small ps-muted"><?= ps_h('Inte bedömd ännu.') ?></p>
                    <?php endif; ?>
                    <?php if (($canRate && $wine['rating'] === null) || $canManage): ?>
                        <div class="ps-inline wine__tools">
                            <?php if ($canRate && $wine['rating'] === null): ?><button type="button" class="ps-btn ps-btn--sm ps-btn--primary" data-wine-rate="<?= $id ?>" data-wine-name="<?= e($wine['title']) ?>"><i class="bx bx-star"></i><?= ps_h('Betygsätt') ?></button><?php endif; ?>
                            <?php if ($canManage): ?>
                                <button type="button" class="ps-btn ps-btn--sm" data-wine-edit="<?= $id ?>" data-title="<?= e($wine['title']) ?>" data-description="<?= e($wine['description']) ?>"><i class="bx bx-edit-alt"></i><?= ps_h('Redigera') ?></button>
                                <button type="button" class="ps-btn ps-btn--sm ps-btn--ghost ps-btn--icon" data-wine-delete="<?= $id ?>" aria-label="Ta bort <?= e($wine['title']) ?>" title="<?= ps_h('Ta bort') ?>"><i class="bx bx-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="ps-empty"><i class="bx bx-wine"></i><div><?= $search !== '' ? ps_t('Inga viner matchar sökningen.') : ps_t('Inga viner ännu.') ?></div></div>
<?php endif; ?>

<?php if ($canRate): ?>
<div class="ps-modal-backdrop" id="wine-rate" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="wine-rate-title" data-wine-rate-form data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="wine-rate-title"><?= ps_h('Betygsätt') ?></h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body">
                <p class="ps-muted" data-wine-rate-name></p>
                <label class="ps-field"><span class="ps-label"><?= ps_h('Betyg:') ?> <strong data-wine-rate-value>7</strong> <?= ps_h('av 10') ?></span><input class="wine-range" type="range" name="rating" min="1" max="10" step="0.5" value="7"></label>
                <label class="ps-field"><span class="ps-label"><?= ps_h('Din kommentar:') ?></span><textarea class="ps-textarea" name="comment" rows="3" maxlength="1000"></textarea></label>
                <p class="ps-help"><?= ps_h('Varje vin får ett betyg. Det går inte att ändra efteråt.') ?></p>
            </div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close><?= ps_h('Avbryt') ?></button><button type="submit" class="ps-btn ps-btn--primary"><?= ps_h('Spara betyg') ?></button></div>
        </div>
    </form>
</div>
<?php endif; ?>
<?php if ($canManage): ?>
<div class="ps-modal-backdrop" id="wine-new" hidden>
    <form class="ps-frame ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="wine-new-title" data-wine-form data-ps-no-loading>
        <div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="wine-new-title"><?= ps_h('Lägg till nytt vin') ?></h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button></div>
            <div class="ps-modal__body">
                <input type="hidden" name="id" value="">
                <label class="ps-field"><span class="ps-label"><?= ps_h('Titel') ?></span><input class="ps-input" name="title" maxlength="190" required></label>
                <label class="ps-field"><span class="ps-label"><?= ps_h('Beskrivning') ?></span><textarea class="ps-textarea" name="description" rows="3" maxlength="2000"></textarea></label>
                <label class="ps-field" data-wine-image><span class="ps-label"><?= ps_h('Vinbild') ?></span><input class="ps-input" type="file" name="image" accept="image/*"></label>
            </div>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close><?= ps_h('Avbryt') ?></button><button type="submit" class="ps-btn ps-btn--primary"><?= ps_h('Spara') ?></button></div>
        </div>
    </form>
</div>
<?php endif; ?>
<?php blog_layout_end(['scripts' => ($canRate || $canManage) ? '<script src="' . e(blog_asset('assets/blog/wines.js')) . '" defer></script>' : '']);
