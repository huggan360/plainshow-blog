<?php /* Story viewer and story upload dialogs, used on the feed. */ ?>
<div class="story-viewer" id="story-viewer" hidden role="dialog" aria-modal="true" aria-label="<?= ps_h('Story') ?>">
    <div class="story-viewer__stage">
        <div class="story-viewer__bars" id="story-bars"></div>
        <div class="story-viewer__head">
            <span class="story-viewer__who" id="story-who"></span>
            <span class="story-viewer__ago ps-small" id="story-ago"></span>
            <span class="ps-topnav__spacer"></span>
            <span class="ps-badge" id="story-views" hidden><i class="bx bx-show"></i><span></span></span>
            <button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" id="story-delete" hidden aria-label="<?= ps_h('Ta bort storyn') ?>"><i class="bx bx-trash"></i></button>
            <button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-story-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button>
        </div>
        <img class="story-viewer__image" id="story-image" alt="">
        <p class="story-viewer__caption" id="story-caption"></p>
        <button type="button" class="story-viewer__nav story-viewer__nav--prev" data-story-step="-1" aria-label="<?= ps_h('Föregående') ?>"></button>
        <button type="button" class="story-viewer__nav story-viewer__nav--next" data-story-step="1" aria-label="<?= ps_h('Nästa') ?>"></button>
    </div>
</div>

<div class="ps-modal-backdrop" id="story-upload" hidden>
    <div class="ps-modal ps-modal--sm" role="dialog" aria-modal="true" aria-labelledby="story-upload-title">
        <section class="ps-frame"><div class="ps-frame__panel">
            <div class="ps-modal__head"><h2 class="ps-modal__title" id="story-upload-title"><?= ps_h('Ny story') ?></h2><button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-modal-close aria-label="<?= ps_h('Stäng') ?>"><i class="bx bx-x"></i></button></div>
            <form class="ps-modal__body" id="story-form" data-ps-no-loading>
                <label class="story-drop" for="story-file">
                    <img id="story-preview" alt="" hidden>
                    <span class="story-drop__hint" id="story-hint"><i class="bx bx-image-add"></i><?= ps_h('Välj en bild') ?></span>
                </label>
                <input class="ps-sr-only" type="file" id="story-file" name="image" accept="image/*" required>
                <div class="ps-field story-form__caption"><label class="ps-label" for="story-caption-input"><?= ps_h('Text (valfritt)') ?></label><input class="ps-input" id="story-caption-input" name="caption" maxlength="300"></div>
                <div class="ps-meter" hidden id="story-progress"><span></span></div>
            </form>
            <div class="ps-modal__foot"><button type="button" class="ps-btn ps-btn--ghost" data-modal-close><?= ps_h('Avbryt') ?></button><button type="submit" form="story-form" class="ps-btn ps-btn--primary" id="story-submit"><?= ps_h('Publicera') ?></button></div>
        </div></section>
    </div>
</div>
