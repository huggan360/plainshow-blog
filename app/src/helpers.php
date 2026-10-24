<?php
/*
 * Small helpers shared by every blog page and API endpoint.
 */

use PlainShow\Kit\Identity;

if (!function_exists('e')) {
    /** HTML-escape anything for output. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** Escape and keep line breaks, for user text such as post bodies and comments. */
function e_lines(?string $value): string
{
    return nl2br(e($value), false);
}

/** Escaped text with line breaks kept and web addresses made into links. */
function e_links(?string $value): string
{
    $html = e_lines($value);
    return (string) preg_replace_callback('~\bhttps?://[^\s<]+[^\s<.,;:!?)\]]~i', fn ($m) => '<a href="' . $m[0] . '" rel="nofollow noopener" target="_blank">' . $m[0] . '</a>', $html);
}

/** Short plain-text excerpt. */
function excerpt(?string $text, int $length = 160): string
{
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)) ?? '');
    return mb_strlen($plain) > $length ? rtrim(mb_substr($plain, 0, $length - 1)) . '…' : $plain;
}

/** Swedish date such as "14 okt 2026". */
function blog_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '';
    }
    $time = strtotime($date);
    if ($time === false) {
        return '';
    }
    $months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    $text = date('j', $time) . ' ' . $months[(int) date('n', $time) - 1] . ' ' . date('Y', $time);
    return $withTime ? $text . ', ' . date('H:i', $time) : $text;
}

/** Relative time in Swedish, for comments and notifications. */
function blog_ago(?string $date): string
{
    $time = $date ? strtotime($date) : false;
    if ($time === false) {
        return '';
    }
    if (strlen(trim((string) $date)) === 10) {
        // A day without a time, like a post's date.
        $days = (int) floor((strtotime('today') - $time) / 86400);
        return match (true) {
            $days <= 0 => 'idag',
            $days === 1 => 'igår',
            $days < 7 => $days . ' d sedan',
            default => blog_date($date),
        };
    }
    $seconds = max(0, time() - $time);
    if ($seconds < 60) {
        return 'nyss';
    }
    $steps = [[3600, 60, 'min'], [86400, 3600, 'tim'], [604800, 86400, 'd']];
    foreach ($steps as [$limit, $unit, $label]) {
        if ($seconds < $limit) {
            return intdiv($seconds, $unit) . ' ' . $label . ' sedan';
        }
    }
    return blog_date($date);
}

function csrf_token(): string
{
    if (empty($_SESSION['blog_csrf'])) {
        $_SESSION['blog_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['blog_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** True when the request carries this session's CSRF token (form field or X-CSRF-Token header). */
function csrf_valid(): bool
{
    // Calls through core's API gateway are signed instead; there is no form to forge.
    if (gateway_identity() !== null) {
        return true;
    }
    $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && isset($_SESSION['blog_csrf']) && hash_equals($_SESSION['blog_csrf'], $sent);
}

/** The signed-in PlainShow identity, or null. */
function current_identity(): ?Identity
{
    return gateway_identity() ?? wocCurrentUser();
}

/** The person a call through core's API gateway is for, once its signature has been checked. */
function gateway_identity(?Identity $set = null): ?Identity
{
    static $identity = null;
    if ($set !== null) {
        $identity = $set;
    }
    return $identity;
}

/**
 * For public pages: the person, if any. Someone signed in to PlainShow
 * elsewhere is signed in here too, without a click (a silent check with the
 * login service, at most every ten minutes).
 */
function public_identity(): ?Identity
{
    $identity = current_identity();
    if ($identity === null) {
        wocAuth()->trySilentSignIn();
    }
    return $identity;
}

/** For pages: sign in if needed. */
function require_identity(): Identity
{
    return wocRequireUser();
}

function blog_can(string $capability): bool
{
    $identity = current_identity();
    return $identity !== null && woc_can($identity, $capability);
}

function woc_can(Identity $identity, string $capability): bool
{
    $granted = array_map('strval', wocPlatformResponse($identity->id())['viewer']['capabilities'] ?? []);
    return in_array('*', $granted, true) || in_array($capability, $granted, true);
}

function woc_is_admin(Identity $identity): bool
{
    return (string) (wocPlatformResponse($identity->id())['viewer']['role']['key'] ?? '') === 'admin';
}

function platform_people(): array
{
    static $people = null;
    if ($people !== null) {
        return $people;
    }
    $identity = current_identity();
    $people = $identity === null ? [] : wocPlatformResponse($identity->id())['people'];
    if ($people !== []) {
        wocSyncPlatformPeople($people);
    }
    return $people;
}

function platform_person(int $userId): ?array
{
    foreach (platform_people() as $person) {
        if ((int) ($person['id'] ?? 0) === $userId) {
            return $person;
        }
    }
    return null;
}

/** The signed-in person's row in the blog's user table. */
function current_member(): ?array
{
    static $member = false;
    if ($member !== false) {
        return $member;
    }
    $identity = current_identity();
    if ($identity === null) {
        return $member = null;
    }
    $stmt = wocDb()->prepare('SELECT user_id, username, firstname, lastname, access_level, image_source FROM `user` WHERE user_id = ?');
    $id = $identity->id();
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $member = ($stmt->get_result()->fetch_assoc() ?: null);
}

/** Full name for a user row. */
function member_name(?array $row): string
{
    if (!$row) {
        return '';
    }
    return trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')) ?: (string) ($row['username'] ?? '');
}

/** Avatar markup (the blog's own avatar style, now part of the PlainShow kit). */
function avatar(?array $row, string $size = '', string $extra = ''): string
{
    $source = trim((string) ($row['image_source'] ?? ''));
    $src = $source !== '' ? wocAvatarResolveSrc($source) : '';
    $name = member_name($row);
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', $name) ?: [], 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    $classes = trim('ps-avatar' . ($size !== '' ? ' ps-avatar--' . $size : '') . ' ' . $extra);
    $inner = $src !== ''
        ? '<img class="ps-avatar__img" src="' . e($src) . '" alt="" loading="lazy">'
        : '<span class="ps-avatar__initials" aria-hidden="true">' . e($initials ?: '?') . '</span>';
    return '<span class="' . e($classes) . '" title="' . e($name) . '">' . $inner . '</span>';
}

/** URL of a post's cover image through the protected image endpoint. */
function post_image_url(?string $path, int $width = 0): string
{
    return $path ? '/image?file=' . rawurlencode(basename((string) $path)) . ($width ? '&w=' . $width : '') : '';
}

function gallery_image_url(?string $source, int $width = 0): string
{
    return $source ? '/image_gallery?file=' . rawurlencode(basename((string) $source)) . ($width ? '&w=' . $width : '') : '';
}

/** Redirect and stop. Only local paths. */
function redirect_to(string $path): never
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        $path = '/';
    }
    header('Location: ' . $path, true, 303);
    exit;
}

/** Flash message for the next page. */
function flash(string $message, string $kind = 'success'): void
{
    $_SESSION['blog_flash'] = ['message' => $message, 'kind' => $kind];
}

function take_flash(): ?array
{
    $flash = $_SESSION['blog_flash'] ?? null;
    unset($_SESSION['blog_flash']);
    return is_array($flash) ? $flash : null;
}

/** Role badge from a blog access level (5 admin, 4 moderator, 1 friend), kept from the original blog. */
function role_badge(int $level): string
{
    return match (true) {
        $level >= 5 => '<span class="ps-badge ps-badge--accent">' . ps_h('Admin') . '</span>',
        $level === 4 => '<span class="ps-badge ps-badge--amber">' . ps_h('Moderator') . '</span>',
        $level >= 1 => '<span class="ps-badge ps-badge--green">' . ps_h('Friend') . '</span>',
        default => '',
    };
}

function platform_role_badge(int $userId, int $fallbackLevel = 0): string
{
    $role = platform_person($userId)['role'] ?? null;
    if (!is_array($role) || trim((string) ($role['name'] ?? '')) === '') {
        return '';
    }
    $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($role['color'] ?? '')) ? (string) $role['color'] : '#777777';
    return '<span class="ps-badge comment__role" style="color:' . e($color) . ';border-color:' . e($color) . ';background:color-mix(in srgb,' . e($color) . ' 14%,transparent)">' . e($role['name']) . '</span>';
}

/** The PlainShow core account page, where profile, picture and password live now. */
function core_account_url(string $section = ''): string
{
    return ps_url('core', '/account' . ($section !== '' ? '/' . $section : ''));
}
