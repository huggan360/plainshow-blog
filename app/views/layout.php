<?php
/*
 * The blog's page frame: PlainShow top navigation, the surf backdrop, the
 * phone tab bar and the footer. Pages call blog_layout_start() and
 * blog_layout_end() around their content.
 *
 * Options: title, description, active (hem|inlagg|album|mina), backdrop (bool),
 *          narrow (bool), wide (bool), body (extra class), head (extra HTML).
 */

function blog_ui_version(): string
{
    static $version = null;
    if ($version === null) {
        $reference = \Composer\InstalledVersions::getReference('plainshow/kit');
        $version = $reference ? substr($reference, 0, 8) : 'dev';
    }
    return $version;
}

function blog_asset(string $path): string
{
    $file = dirname(__DIR__, 2) . '/public/' . ltrim($path, '/');
    return '/' . ltrim($path, '/') . '?v=' . (is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : blog_ui_version());
}

/** Other PlainShow services the person can open, for the app switcher. */
function blog_apps(): array
{
    $identity = current_identity();
    $services = $identity ? array_map('strval', wocPlatformResponse($identity->id())['viewer']['services'] ?? []) : [];
    $apps = [
        'core' => ['Dashboard', 'bx-grid-alt', ps_url('core')],
        'blog' => ['Blog', 'bx-news', '/'],
        'latex' => ['LaTeX', 'bx-code-curly', ps_url('latex')],
        'split' => ['Split', 'bx-wallet', ps_url('split')],
        'cloud' => ['Cloud', 'bx-cloud', ps_url('cloud')],
        'web' => ['Web', 'bx-server', ps_url('web')],
    ];
    $list = [];
    foreach ($apps as $id => [$name, $icon, $url]) {
        if ($url !== '' && ($id === 'core' || in_array($id, $services, true))) {
            $list[] = ['id' => $id, 'name' => $name, 'icon' => $icon, 'url' => $url];
        }
    }
    return $list;
}

function blog_notification_count(): int
{
    $member = current_member();
    if (!$member) {
        return 0;
    }
    $stmt = wocDb()->prepare('SELECT COUNT(*) FROM notiser WHERE user_id = ?');
    $id = (int) $member['user_id'];
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_row()[0];
}

function blog_layout_start(array $options = []): void
{
    $title = $options['title'] ?? ps_t('Week of Bali');
    $description = $options['description'] ?? ps_t('Week of Bali, resor och vänner.');
    $active = $options['active'] ?? '';
    $identity = current_identity();
    $member = current_member();
    $notifications = $identity ? blog_notification_count() : 0;
    $links = [
        'hem' => ['Hem', 'bx-home-alt-2', '/'],
        'inlagg' => ['Inlägg', 'bx-collection', '/blogfeed'],
        'album' => ['Album', 'bx-photo-album', '/gallery'],
        'mina' => [ps_t('Mina sidor'), 'bx-user', '/dashboard'],
    ];
    $bodyClasses = trim('ps blog' . (!empty($options['backdrop']) ? ' blog--backdrop' : '') . ' ' . ($options['body'] ?? ''));
    $flash = take_flash();
    ?>
<!doctype html>
<html lang="<?= e(\PlainShow\Kit\Lang::current()) ?>" data-theme="dark"<?= \PlainShow\Kit\Theme::attributes() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#151515">
    <meta name="description" content="<?= e($description) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> | Week of Bali</title>
    <link rel="icon" href="/ui/brand/favicon.ico?v=<?= e(blog_ui_version()) ?>" sizes="any">
    <link rel="icon" href="/ui/brand/plainshow-icon.webp?v=<?= e(blog_ui_version()) ?>" type="image/webp">
    <link rel="apple-touch-icon" href="/ui/brand/apple-touch-icon.png?v=<?= e(blog_ui_version()) ?>">
    <link rel="preload" href="/ui/fonts/source-serif-4-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/ui/fonts/outfit-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/ui/css/boxicons.css?v=<?= e(blog_ui_version()) ?>">
    <link rel="stylesheet" href="/ui/css/plainshow.css?v=<?= e(blog_ui_version()) ?>">
    <link rel="stylesheet" href="<?= e(blog_asset('assets/blog/blog.css')) ?>">
    <script>try { var t = localStorage.getItem('plainshow-theme'); if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t); } catch (e) {}</script>
    <script>window.PS_I18N = <?= json_encode((object) \PlainShow\Kit\Lang::dictionary(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>; window.psT = function (s, p) { var o = window.PS_I18N[s] || s; if (p) Object.keys(p).forEach(function (k) { o = o.split('{' + k + '}').join(String(p[k])); }); return o; };</script>
    <script src="/ui/js/plainshow.js?v=<?= e(blog_ui_version()) ?>" defer></script>
    <script src="<?= e(blog_asset('assets/blog/blog.js')) ?>" defer></script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-08C37V4PPK"></script>
    <script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', 'G-08C37V4PPK');</script>
    <?= $options['head'] ?? '' ?>
</head>
<body class="<?= e($bodyClasses) ?>">
<?php if (!empty($options['backdrop'])): ?><div class="blog-backdrop" aria-hidden="true"></div><?php endif; ?>
<header class="ps-topnav blog-nav">
    <div class="ps-topnav__inner">
        <button type="button" class="ps-btn ps-btn--ghost ps-btn--icon ps-topnav__toggle" data-ps-topnav-toggle aria-expanded="false" aria-label="<?= ps_h('Meny') ?>"><i class="bx bx-menu"></i></button>
        <a class="ps-brand ps-topnav__brand" href="/">
            <img class="ps-brand__logo" src="/ui/brand/plainshow-logo.webp?v=<?= e(blog_ui_version()) ?>" alt="<?= ps_h('PlainShow') ?>" width="92" height="30">
            <span class="ps-brand__suffix"><?= ps_h('Blog') ?></span>
        </a>
        <?php if ($identity): ?>
        <nav class="ps-topnav__links" aria-label="<?= ps_h('Huvudmeny') ?>">
            <?php foreach ($links as $key => [$label, $icon, $href]): ?>
                <a class="ps-topnav__link<?= $active === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><i class="bx <?= e($icon) ?>" aria-hidden="true"></i><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <span class="ps-topnav__spacer"></span>
        <div class="ps-topnav__actions">
            <?php if ($identity): ?>
                <form class="ps-topnav__search" action="/search" method="get" role="search">
                    <i class="bx bx-search" aria-hidden="true"></i>
                    <input class="ps-input" type="search" name="query" placeholder="Sök" value="<?= e($_GET['query'] ?? '') ?>" aria-label="<?= ps_h('Sök inlägg och personer') ?>">
                </form>
                <?php if (blog_can('blog.post.create')): ?>
                    <a class="ps-btn ps-btn--primary ps-btn--sm blog-nav__new" href="/upload"><i class="bx bx-plus"></i><span><?= ps_h('Nytt inlägg') ?></span></a>
                <?php endif; ?>
                <a class="ps-btn ps-btn--ghost ps-btn--icon blog-bell" href="/notiser" aria-label="Notiser<?= $notifications ? " ($notifications)" : '' ?>">
                    <i class="bx bx-bell"></i><?php if ($notifications > 0): ?><span class="blog-bell__count"><?= $notifications > 99 ? '99+' : $notifications ?></span><?php endif; ?>
                </a>
                <div class="ps-dropdown" data-ps-dropdown>
                    <button type="button" class="ps-btn ps-btn--ghost ps-btn--icon" data-ps-dropdown-trigger aria-expanded="false" aria-label="<?= ps_h('Byt tjänst') ?>"><i class="bx bx-grid-small"></i></button>
                    <div class="ps-switcher__panel" data-ps-dropdown-menu hidden>
                        <div class="ps-switcher__grid">
                            <?php foreach (blog_apps() as $app): ?>
                                <a class="ps-switcher__app<?= $app['id'] === 'blog' ? ' is-current' : '' ?>" href="<?= e($app['url']) ?>"><i class="bx <?= e($app['icon']) ?>" aria-hidden="true"></i><?= e($app['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="ps-dropdown" data-ps-dropdown>
                    <button type="button" class="ps-btn ps-btn--ghost blog-nav__account" data-ps-dropdown-trigger aria-expanded="false" aria-label="<?= ps_h('Konto') ?>"><?= avatar($member, 'sm') ?></button>
                    <div class="ps-dropdown__menu" data-ps-dropdown-menu hidden>
                        <div class="ps-dropdown__head"><?= avatar($member) ?><div class="blog-nav__who"><strong><?= e(member_name($member)) ?></strong><span class="ps-muted ps-small">@<?= e($member['username'] ?? '') ?></span></div></div>
                        <div class="ps-dropdown__sep"></div>
                        <a class="ps-dropdown__item" href="<?= e(ps_url('core', '/account')) ?>"><i class="bx bx-user"></i><?= ps_h('Konto') ?></a>
                        <a class="ps-dropdown__item" href="/dashboard"><i class="bx bx-grid-alt"></i><?= ps_h('Mina sidor') ?></a>
                        <a class="ps-dropdown__item" href="/myposts"><i class="bx bx-collection"></i><?= ps_h('Mina inlägg') ?></a>
                        <a class="ps-dropdown__item" href="/profile?user_id=<?= (int) ($member['user_id'] ?? 0) ?>"><i class="bx bx-id-card"></i><?= ps_h('Min profil') ?></a>
                        <?php if (Blog\Domain\Admin::any($identity)): ?><a class="ps-dropdown__item" href="/admin"><i class="bx bx-shield-quarter"></i><?= ps_h('Administration') ?></a><?php endif; ?>
                        <?= \PlainShow\Kit\Lang::menuSwitch() ?>
                        <button type="button" class="ps-dropdown__item" data-ps-theme-toggle><i class="bx bx-adjust"></i><span data-ps-theme-label><?= ps_h('Ljust tema') ?></span></button>
                        <div class="ps-dropdown__sep"></div>
                        <a class="ps-dropdown__item" href="/auth/logout"><i class="bx bx-log-out"></i><?= ps_h('Logga ut') ?></a>
                    </div>
                </div>
            <?php else: ?>
                <a class="ps-btn ps-btn--primary ps-btn--sm" href="/signin?redirect=<?= e(rawurlencode($_SERVER['REQUEST_URI'] ?? '/')) ?>"><?= ps_h('Logga in') ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($identity): ?>
    <div class="ps-topnav__sheet">
        <form action="/search" method="get" role="search" class="blog-nav__sheet-search"><input class="ps-input" type="search" name="query" placeholder="<?= ps_h('Sök inlägg och personer') ?>" aria-label="<?= ps_h('Sök') ?>"></form>
        <?php foreach ($links as $key => [$label, $icon, $href]): ?>
            <a class="ps-topnav__link<?= $active === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>"><i class="bx <?= e($icon) ?>"></i><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="ps-topnav__link" href="/wines"><i class="bx bx-wine"></i><?= ps_h('Viner') ?></a>
        <?php foreach (blog_apps() as $app): if ($app['id'] === 'blog') { continue; } ?>
            <a class="ps-topnav__link" href="<?= e($app['url']) ?>"><i class="bx <?= e($app['icon']) ?>"></i><?= e($app['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</header>
<?php if ($flash): ?>
<div class="ps-toasts" aria-live="polite"><div class="ps-toast" role="<?= $flash['kind'] === 'error' ? 'alert' : 'status' ?>"><i class="bx <?= $flash['kind'] === 'error' ? 'bx-error-circle blog-toast--error' : 'bx-check-circle blog-toast--ok' ?>"></i><span><?= e($flash['message']) ?></span></div></div>
<?php endif; ?>
<main id="main" class="ps-page<?= !empty($options['narrow']) ? ' ps-page--narrow' : '' ?><?= !empty($options['wide']) ? ' blog-page--wide' : '' ?>">
<?php
}

function blog_layout_end(array $options = []): void
{
    $identity = current_identity();
    $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    ?>
</main>
<footer class="blog-footer">
    <div class="blog-footer__inner">
        <span class="ps-serif"><?= ps_h('Week of Bali') ?></span>
        <span class="ps-muted ps-small"><?= ps_h('En del av') ?> <a href="<?= e(ps_url('core')) ?>"><?= ps_h('PlainShow') ?></a></span>
    </div>
</footer>
<?php if ($identity): ?>
<nav class="ps-tabbar" aria-label="<?= ps_h('Snabbmeny') ?>">
    <a class="ps-tabbar__item<?= $current === 'blogfeed' ? ' is-active' : '' ?>" href="/blogfeed"><i class="bx bx-collection"></i><?= ps_h('Inlägg') ?></a>
    <a class="ps-tabbar__item<?= $current === 'gallery' ? ' is-active' : '' ?>" href="/gallery"><i class="bx bx-photo-album"></i><?= ps_h('Album') ?></a>
    <?php if (blog_can('blog.post.create')): ?><a class="ps-tabbar__item blog-tab-new<?= $current === 'upload' ? ' is-active' : '' ?>" href="/upload"><i class="bx bx-plus-circle"></i><?= ps_h('Skriv') ?></a><?php endif; ?>
    <a class="ps-tabbar__item<?= $current === 'notiser' ? ' is-active' : '' ?>" href="/notiser"><i class="bx bx-bell"></i><?= ps_h('Notiser') ?></a>
    <a class="ps-tabbar__item<?= in_array($current, ['dashboard', 'myposts'], true) ? ' is-active' : '' ?>" href="/dashboard"><i class="bx bx-user"></i><?= ps_h('Mina sidor') ?></a>
</nav>
<?php endif; ?>
<?= $options['scripts'] ?? '' ?>
</body>
</html>
<?php
}
