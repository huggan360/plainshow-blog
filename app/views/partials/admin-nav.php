<?php
/** The admin area menu. Needs $adminPage. */
use Blog\Domain\Admin;

$adminIdentity = current_identity();
?>
<div class="admin-head">
    <div>
        <p class="ps-eyebrow ps-eyebrow--accent"><?= ps_h('Admin') ?></p>
        <h1 class="ps-title blog-head__title"><?= e(Admin::PAGES[$adminPage][0]) ?></h1>
    </div>
    <nav class="ps-tabs admin-tabs" aria-label="<?= ps_h('Admin') ?>">
        <?php foreach (Admin::PAGES as $key => [$label, $icon, $href]): if (!Admin::can($adminIdentity, $key)) { continue; } ?>
            <a class="ps-tab<?= $key === $adminPage ? ' is-active' : '' ?>" href="<?= $href ?>"<?= $key === $adminPage ? ' aria-current="page"' : '' ?>><i class="bx <?= $icon ?>" aria-hidden="true"></i><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if (woc_is_admin($adminIdentity)): ?><a class="ps-tab" href="<?= e(ps_url('core', '/admin/users')) ?>"><i class="bx bx-group" aria-hidden="true"></i><?= ps_h('Konton') ?><i class="bx bx-right-top-arrow-circle" aria-hidden="true"></i></a><?php endif; ?>
    </nav>
</div>
