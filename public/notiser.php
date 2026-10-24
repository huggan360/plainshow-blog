<?php
// Notiser: likes, comments, follows, Split and notices from the admins.
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/views/layout.php';

use Blog\Domain\Notifications;

$identity = require_identity();
$notices = Notifications::forUser($identity->id());
$clearable = count(array_filter($notices, fn ($n) => $n['type'] !== 'admin'));

$menu = 'notiser';
blog_layout_start(['title' => ps_t('Notiser'), 'active' => 'mina', 'body' => 'blog-notices-page']);
?>
<div class="member">
    <?php include dirname(__DIR__) . '/app/views/partials/member-menu.php'; ?>
    <div class="member-main">
        <div class="blog-head ps-page-head">
            <div>
                <h1 class="ps-title blog-head__title"><?= ps_h('Notiser') ?></h1>
                <p class="blog-head__text"><?= ps_h('Alla notiser') ?></p>
            </div>
            <?php if ($clearable): ?><button type="button" class="ps-btn" data-notices-clear><i class="bx bx-check-double"></i><span><?= ps_h('Rensa alla') ?></span></button><?php endif; ?>
        </div>
        <section class="ps-frame"<?= $notices ? '' : ' hidden' ?> data-notices>
            <div class="ps-frame__panel notices">
                <ul class="ps-list">
                    <?php foreach ($notices as $notice): $admin = $notice['type'] === 'admin'; ?>
                        <li class="ps-row notice<?= $admin ? ' notice--admin' : '' ?>" id="notice-<?= (int) $notice['id'] ?>" data-type="<?= e($notice['type']) ?>">
                            <span class="notice__icon notice__icon--<?= e($notice['type']) ?>"><i class="bx <?= e($notice['icon']) ?>" aria-hidden="true"></i></span>
                            <div class="ps-row__body">
                                <p class="notice__text"><?php if ($admin): ?><span class="ps-badge ps-badge--accent"><?= ps_h('Admin') ?></span> <?php endif; ?><?= e($notice['content']) ?></p>
                                <div class="ps-row__meta"><?= e(blog_ago($notice['date'])) ?></div>
                            </div>
                            <div class="ps-row__action">
                                <?php if ($notice['href']): ?><a class="ps-btn ps-btn--sm" href="<?= e($notice['href']) ?>"><?= ps_h('Visa') ?></a><?php endif; ?>
                                <?php if (!$admin): ?><button type="button" class="ps-btn ps-btn--sm ps-btn--ghost ps-btn--icon" data-notice-delete="<?= (int) $notice['id'] ?>" aria-label="<?= ps_h('Ta bort notisen') ?>" title="<?= ps_h('Ta bort') ?>"><i class="bx bx-x"></i></button><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <div class="ps-empty" data-notices-empty<?= $notices ? ' hidden' : '' ?>><i class="bx bx-bell-off"></i><div><?= ps_h('Inga notiser.') ?></div></div>
    </div>
</div>
<?php blog_layout_end(['scripts' => '<script src="' . e(blog_asset('assets/blog/notices.js')) . '" defer></script>']);
