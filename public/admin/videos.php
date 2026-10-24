<?php
// Admin: the films on the home page.
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/views/layout.php';

use Blog\Domain\ChunkUpload;
use Blog\Domain\Videos;

$adminPage = 'videos';
require dirname(__DIR__, 2) . '/app/views/admin-guard.php';
$videos = Videos::listing(false);

blog_layout_start(['title' => ps_t('Filmer'), 'active' => '']);
include dirname(__DIR__, 2) . '/app/views/partials/admin-nav.php';
?>
<section class="ps-frame admin-upload">
    <form class="ps-frame__panel" data-video-form data-ps-no-loading>
        <h2 class="ps-frame__title"><?= ps_h('Ladda upp film') ?></h2>
        <p class="ps-frame__text"><?= ps_h('MP4, MOV eller WebM, högst 1 GB. Filmen skickas i delar, så den klarar dåliga anslutningar.') ?></p>
        <div class="admin-upload__grid">
            <label class="uploader__drop admin-upload__drop">
                <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm" required data-video-file>
                <i class="bx bx-movie-play" aria-hidden="true"></i>
                <strong data-video-name><?= ps_h('Välj en film') ?></strong>
            </label>
            <div>
                <label class="ps-field"><span class="ps-label"><?= ps_h('Innehåll') ?></span><textarea class="ps-textarea" name="content" rows="4" maxlength="2000" placeholder="<?= ps_h('Vad händer i filmen?') ?>"></textarea></label>
                <label class="ps-check"><input type="checkbox" name="is_public" value="1" checked><span><?= ps_h('Gör videon publik?') ?></span></label>
            </div>
        </div>
        <div class="uploader__summary" data-video-progress hidden><div class="ps-between"><span data-video-status></span></div><div class="ps-meter"><span data-video-bar style="width: 0%"></span></div></div>
        <div class="ps-frame__foot"><button type="submit" class="ps-btn ps-btn--primary"><i class="bx bx-upload"></i><?= ps_h('Ladda upp') ?></button></div>
    </form>
</section>

<section class="blog-section">
    <div class="blog-section__head"><h2 class="blog-section__title"><?= ps_h('Alla filmer') ?></h2></div>
    <?php if ($videos): ?>
        <div class="ps-frames">
            <?php foreach ($videos as $video): $id = (int) $video['id']; $public = (int) $video['is_public'] === 1; ?>
                <article class="ps-frame video-card" id="video-<?= $id ?>">
                    <div class="ps-frame__panel">
                        <video controls preload="metadata" playsinline src="/assets/<?= e($video['image']) ?>"></video>
                        <div class="video-card__body">
                            <textarea class="ps-textarea" rows="2" maxlength="2000" data-video-text="<?= $id ?>" aria-label="<?= ps_h('Innehåll') ?>"><?= e($video['content']) ?></textarea>
                            <div class="ps-between">
                                <span class="ps-small ps-muted"><?= e(member_name($video)) ?>, <?= e(blog_date($video['date'])) ?></span>
                                <div class="ps-inline">
                                    <label class="ps-check"><input type="checkbox" data-video-public="<?= $id ?>"<?= $public ? ' checked' : '' ?>><span><?= ps_h('Publik') ?></span></label>
                                    <button type="button" class="ps-btn ps-btn--sm ps-btn--danger" data-video-delete="<?= $id ?>"><i class="bx bx-trash"></i><?= ps_h('Radera') ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="ps-empty"><i class="bx bx-movie-play"></i><div><?= ps_h('Inga filmer ännu.') ?></div></div>
    <?php endif; ?>
</section>
<script type="application/json" id="admin-data"><?= json_encode(['chunk' => ChunkUpload::CHUNK_BYTES]) ?></script>
<?php blog_layout_end(['scripts' => '<script src="' . e(blog_asset('assets/blog/admin.js')) . '" defer></script>']);
