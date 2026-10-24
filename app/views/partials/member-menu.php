<?php
/**
 * The "Mina sidor" side menu (the blog's original account menu, now in the kit).
 * Needs $menu: dashboard, myposts or notiser.
 */
use Blog\Domain\Notifications;

$menuMember = current_member();
$menuIdentity = current_identity();
$menuCount = Notifications::count($menuIdentity->id());
$menuItems = [
    'dashboard' => ['Senaste', 'bx-collection', '/dashboard'],
    'myposts' => [ps_t('Mina inlägg'), 'bx-edit', '/myposts'],
    'notiser' => ['Notiser', 'bx-bell', '/notiser'],
];
?>
<aside class="member-side">
    <div class="member-side__who">
        <?= avatar($menuMember, 'lg') ?>
        <div>
            <strong><?= e(member_name($menuMember)) ?></strong>
            <div class="ps-inline member-side__meta"><span class="ps-muted ps-small">@<?= e($menuMember['username'] ?? '') ?></span><?= platform_role_badge($menuIdentity->id(), (int) ($menuMember['access_level'] ?? 0)) ?></div>
        </div>
    </div>
    <nav class="ps-account-menu" aria-label="<?= ps_h('Mina sidor') ?>">
        <?php foreach ($menuItems as $key => [$label, $icon, $href]): ?>
            <a class="ps-account-menu__item<?= $menu === $key ? ' is-active' : '' ?>" href="<?= $href ?>"<?= $menu === $key ? ' aria-current="page"' : '' ?>>
                <i class="bx <?= $icon ?>" aria-hidden="true"></i><?= e($label) ?>
                <?php if ($key === 'notiser' && $menuCount > 0): ?><span class="member-side__count" data-notification-count><?= $menuCount ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <a class="ps-account-menu__item" href="<?= e(core_account_url()) ?>"><i class="bx bx-id-card" aria-hidden="true"></i><?= ps_h('Konto') ?><i class="bx bx-right-top-arrow-circle member-side__out" aria-hidden="true"></i></a>
        <?php if (Blog\Domain\Admin::any($menuIdentity)): ?>
            <a class="ps-account-menu__item" href="/admin"><i class="bx bx-shield-quarter" aria-hidden="true"></i><?= ps_h('Admin') ?></a>
        <?php endif; ?>
        <a class="ps-account-menu__item" href="/logout"><i class="bx bx-log-out" aria-hidden="true"></i><?= ps_h('Logga ut') ?></a>
    </nav>
</aside>
