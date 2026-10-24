<?php
// Write a new post, or edit one with ?edit={id}: a title and blocks of text and pictures.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Posts;

$identity = require_identity();
$editId = (int) ($_GET['edit'] ?? 0);
$post = null;
$sections = [['type' => 'text', 'content' => '']];
if ($editId > 0) {
    $post = Posts::find($editId, $identity->id());
    if (!$post || !Posts::canEdit($identity, $post)) {
        flash(ps_t('Du kan bara redigera dina egna inlägg.'), 'error');
        redirect_to('/blogfeed');
    }
    $conn = wocDb();
    require_once dirname(__DIR__) . '/app/backend/posts-back.php';
    $sections = postsFetchPostForEditor($conn, $editId)['sections'] ?: $sections;
} elseif (!blog_can('blog.post.create')) {
    flash(ps_t('Du har inte behörighet att skriva inlägg.'), 'error');
    redirect_to('/blogfeed');
}

// The editor only sees picture URLs, never storage paths; it sends back the index.
$existing = [];
foreach ($sections as $index => $section) {
    if ($section['type'] === 'image') {
        $existing[] = $section['image_path'];
        $sections[$index] = ['type' => 'image', 'existing' => count($existing) - 1, 'url' => post_image_url($section['image_path'], 480)];
    }
}
$_SESSION['blog_editor'][$editId] = $existing;

$canPublic = blog_can('blog.post.moderate');
$isPublic = $post ? (int) $post['is_public'] === 1 : false;
$editor = ['post' => $editId ?: null, 'sections' => $sections];

blog_layout_start([
    'title' => $post ? ps_t('Redigera inlägg') : ps_t('Nytt inlägg'),
    'active' => 'inlagg',
    'narrow' => true,
    'body' => 'blog-editor-page',
]);
?>
<a class="blog-back" href="<?= $post ? '/view_blog?post_id=' . $editId : '/myposts' ?>"><i class="bx bx-left-arrow-alt" aria-hidden="true"></i><?= $post ? ps_t('Till inlägget') : ps_t('Mina inlägg') ?></a>
<div class="blog-head ps-page-head">
    <div>
        <h1 class="ps-title blog-head__title"><?= $post ? ps_t('Redigera inlägg') : ps_t('Nytt inlägg') ?></h1>
        <p class="blog-head__text"><?= ps_h('Skriv text och lägg till bilder i den ordning de ska visas.') ?></p>
    </div>
    <div class="ps-tabs editor-mode" role="tablist" aria-label="<?= ps_h('Läge') ?>">
        <button type="button" class="ps-tab is-active" role="tab" aria-selected="true" data-editor-mode="write"><?= ps_h('Skriv') ?></button>
        <button type="button" class="ps-tab" role="tab" aria-selected="false" data-editor-mode="preview"><?= ps_h('Förhandsgranska') ?></button>
    </div>
</div>

<form class="editor" id="editor" data-ps-no-loading novalidate>
    <section class="ps-frame editor__frame">
        <div class="ps-frame__panel">
            <label class="ps-sr-only" for="editor-title"><?= ps_h('Titel') ?></label>
            <input class="editor__title" id="editor-title" name="title" maxlength="255" placeholder="Titel" autocomplete="off" required value="<?= e($post['title'] ?? '') ?>">
            <div class="editor__options">
                <?php if ($canPublic): ?>
                    <label class="ps-check editor__public">
                        <input type="checkbox" name="is_public" value="1"<?= $isPublic ? ' checked' : '' ?>>
                        <span><?= ps_h('Offentligt inlägg') ?></span>
                        <span class="ps-muted"><?= ps_h('Synligt för alla, även utan inloggning.') ?></span>
                    </label>
                <?php else: ?>
                    <span class="ps-badge"><i class="bx bx-lock-alt" aria-hidden="true"></i><?= $isPublic ? ps_t('Offentligt') : ps_t('Bara inloggade') ?></span>
                <?php endif; ?>
                <span class="ps-muted ps-small editor__summary" data-editor-summary></span>
            </div>
            <div class="editor__blocks" data-editor-blocks></div>
            <div class="editor__add">
                <button type="button" class="ps-btn" data-editor-add="text"><i class="bx bx-paragraph"></i><span><?= ps_h('Text') ?></span></button>
                <button type="button" class="ps-btn" data-editor-add="image"><i class="bx bx-images"></i><span><?= ps_h('Bilder') ?></span></button>
                <span class="ps-muted ps-small editor__hint"><?= ps_h('Du kan också dra in bilder var som helst.') ?></span>
            </div>
        </div>
    </section>
    <article class="post editor__preview" data-editor-preview hidden></article>

    <div class="editor__bar">
        <div class="editor__status" data-editor-status aria-live="polite">
            <span data-editor-status-text><?= $post ? ps_t('Ändringarna sparas när du klickar på Spara.') : ps_t('Utkastet sparas på den här enheten medan du skriver.') ?></span>
            <div class="ps-meter editor__progress" hidden><span data-editor-progress style="width: 0%"></span></div>
        </div>
        <a class="ps-btn ps-btn--ghost" href="<?= $post ? '/view_blog?post_id=' . $editId : '/blogfeed' ?>"><?= ps_h('Avbryt') ?></a>
        <button type="submit" class="ps-btn ps-btn--primary ps-btn--lg" data-editor-submit><i class="bx <?= $post ? 'bx-save' : 'bx-send' ?>"></i><span><?= $post ? ps_t('Spara') : ps_t('Publicera') ?></span></button>
    </div>
</form>
<input type="file" accept="image/*" multiple hidden data-editor-file>
<div class="editor-drop" data-editor-drop hidden><div><i class="bx bx-images"></i><p><?= ps_h('Släpp bilderna här') ?></p></div></div>
<script type="application/json" id="editor-data"><?= json_encode($editor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php blog_layout_end(['scripts' => '<script src="' . e(blog_asset('assets/blog/editor.js')) . '" defer></script>']);
