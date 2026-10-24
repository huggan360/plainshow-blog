<?php
// Admin: notices to members. They appear first under Notiser and as a push.
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/views/layout.php';

use Blog\Domain\Admin;

$adminPage = 'notices';
require dirname(__DIR__, 2) . '/app/views/admin-guard.php';
$members = Admin::members();
$notices = Admin::notices();

blog_layout_start(['title' => ps_t('Adminnotiser'), 'active' => '']);
include dirname(__DIR__, 2) . '/app/views/partials/admin-nav.php';
?>
<div class="ps-frames">
    <section class="ps-frame">
        <form class="ps-frame__panel" data-notice-form data-ps-no-loading>
            <h2 class="ps-frame__title"><?= ps_h('Skicka adminmeddelande') ?></h2>
            <label class="ps-field admin-notice__message"><span class="ps-label"><?= ps_h('Meddelande') ?></span><textarea class="ps-textarea" name="message" rows="4" maxlength="1000" required></textarea></label>
            <div class="ps-between admin-notice__head"><span class="ps-label"><?= ps_h('Mottagare') ?></span><button type="button" class="ps-btn ps-btn--sm ps-btn--ghost" data-notice-all><?= ps_h('Välj alla') ?></button></div>
            <div class="admin-recipients">
                <?php foreach ($members as $member): ?>
                    <label class="admin-recipient"><input type="checkbox" name="users" value="<?= (int) $member['user_id'] ?>"><?= avatar($member, 'xs') ?><span><?= e(member_name($member)) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="ps-frame__foot"><button type="submit" class="ps-btn ps-btn--primary"><i class="bx bx-send"></i><?= ps_h('Skicka') ?></button></div>
        </form>
    </section>
    <section class="ps-frame">
        <div class="ps-frame__panel">
            <h2 class="ps-frame__title"><?= ps_h('Alla adminnotiser') ?></h2>
            <?php if ($notices): ?>
                <ul class="ps-list ps-frame__body">
                    <?php foreach ($notices as $notice): ?>
                        <li class="ps-row admin-sent" id="notice-group-<?= (int) $notice['assign_id'] ?>">
                            <div class="ps-row__body">
                                <p class="notice__text"><?= e_lines($notice['content']) ?></p>
                                <div class="ps-row__meta" title="<?= e($notice['names']) ?>"><?= (int) $notice['recipients'] ?> <?= ps_h('mottagare,') ?> <?= e(blog_ago($notice['date'])) ?></div>
                            </div>
                            <button type="button" class="ps-btn ps-btn--sm ps-btn--ghost ps-btn--icon" data-notice-delete="<?= (int) $notice['assign_id'] ?>" aria-label="<?= ps_h('Radera') ?>" title="<?= ps_h('Radera') ?>"><i class="bx bx-trash"></i></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="ps-muted ps-frame__body"><?= ps_h('Det finns inga adminnotiser.') ?></p>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php blog_layout_end(['scripts' => '<script src="' . e(blog_asset('assets/blog/admin.js')) . '" defer></script>']);
