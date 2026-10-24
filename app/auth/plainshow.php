<?php
/*
 * PlainShow sign-in for the blog.
 *
 * Every page that used the blog's own login now signs in through
 * login.plainshow.se. On sign-in the person's row in the blog's `user`
 * table is created or refreshed from their PlainShow account, and
 * $_SESSION['user_id'] is set exactly as before, so the pages keep working.
 *
 *   wocSession();      start the session (public pages)
 *   wocRequireUser();  sign in if needed, return the PlainShow identity
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PlainShow\Kit\Identity;
use PlainShow\Kit\PhpSessionAuth;
use PlainShow\Kit\Widgets;

if (!function_exists('wocSession')) {
    function wocSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.save_path', dirname(__DIR__) . '/sessions');
        ini_set('session.use_strict_mode', '1');
        session_name('woc_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            // Always true in production; plain http only on a local dev server.
            'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_NAME'] ?? '') !== 'localhost',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Blog access levels: 5 admin, 4 moderator, 1 member (shown as "Friend"). */
    function wocAccessLevel(string $role): int
    {
        return ['admin' => 5, 'moderator' => 4, 'member' => 1][$role] ?? 0;
    }

    /** Creates or refreshes the blog's copy of a PlainShow account. */
    function wocSyncUser(Identity $identity): void
    {
        require dirname(__DIR__, 2) . '/connect.php';
        $id = $identity->id();
        $username = mb_substr($identity->username(), 0, 15);
        $first = mb_substr($identity->firstName(), 0, 30);
        $last = mb_substr($identity->lastName(), 0, 30);
        $level = wocAccessLevel($identity->role());
        $picture = $identity->picture();

        $stmt = $conn->prepare('SELECT `user_id` FROM `user` WHERE `user_id` = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();

        if ($exists) {
            $stmt = $conn->prepare('UPDATE `user` SET `username` = ?, `firstname` = ?, `lastname` = ?, `access_level` = ?, `image_source` = COALESCE(?, `image_source`) WHERE `user_id` = ?');
            $stmt->bind_param('sssisi', $username, $first, $last, $level, $picture, $id);
        } else {
            // Passwords live in PlainShow now; the blog keeps none.
            $stmt = $conn->prepare("INSERT INTO `user` (`user_id`, `username`, `password`, `firstname`, `lastname`, `access_level`, `image_source`) VALUES (?, ?, '', ?, ?, ?, ?)");
            $source = $picture ?? '';
            $stmt->bind_param('isssis', $id, $username, $first, $last, $level, $source);
        }
        $stmt->execute();
        $stmt->close();
    }

    function wocAuth(): PhpSessionAuth
    {
        static $auth = null;
        wocSession();
        if ($auth === null) {
            $config = require dirname(__DIR__, 2) . '/config/plainshow.php';
            $auth = new PhpSessionAuth($config, function (Identity $identity): void {
                wocSyncUser($identity);
                $_SESSION['user_id'] = $identity->id();
            });
        }

        return $auth;
    }

    function wocRequireUser(): Identity
    {
        $identity = wocAuth()->requireUser();
        $status = wocPlatformStatus($identity->id());
        if ($status !== 200) {
            http_response_code($status === 403 ? 403 : 503);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . ps_h('Blog') . '</title><p>', $status === 403 ? ps_t('You no longer have access to Blog.') : ps_t('PlainShow is temporarily unavailable.'), '</p><a href="' . htmlspecialchars(ps_url('core', '/'), ENT_QUOTES) . '">' . ps_h('PlainShow') . '</a></html>';
            exit;
        }
        $_SESSION['user_id'] = $identity->id();

        return $identity;
    }

    /** The signed-in identity, or null on public pages. */
    function wocCurrentUser(): ?Identity
    {
        $identity = wocAuth()->user();
        if ($identity !== null && wocPlatformStatus($identity->id()) === 200) {
            $_SESSION['user_id'] = $identity->id();
        } else {
            $identity = null;
            unset($_SESSION['user_id']);
        }

        return $identity;
    }

    function wocPlatformStatus(int $userId): int
    {
        return wocPlatformResponse($userId)['status'];
    }

    function wocSyncPlatformPeople(array $people): void
    {
        require dirname(__DIR__, 2) . '/connect.php';
        $statement = $conn->prepare("INSERT INTO `user` (`user_id`, `username`, `password`, `firstname`, `lastname`, `access_level`, `image_source`)
            VALUES (?, ?, '', ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `firstname` = VALUES(`firstname`), `lastname` = VALUES(`lastname`), `access_level` = VALUES(`access_level`), `image_source` = VALUES(`image_source`)");
        foreach ($people as $person) {
            $id = (int) ($person['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $username = mb_substr((string) ($person['username'] ?? ''), 0, 15);
            $first = mb_substr((string) ($person['firstName'] ?? ''), 0, 30);
            $last = mb_substr((string) ($person['lastName'] ?? ''), 0, 30);
            $role = (string) ($person['role']['key'] ?? 'user');
            $level = ['admin' => 5, 'moderator' => 4, 'user' => 1][$role] ?? 1;
            $picture = mb_substr((string) ($person['avatar'] ?? ''), 0, 255);
            $statement->bind_param('isssis', $id, $username, $first, $last, $level, $picture);
            $statement->execute();
        }
        $statement->close();
    }

    function wocPlatformResponse(int $userId): array
    {
        static $responses = [];
        if (isset($responses[$userId])) {
            return $responses[$userId];
        }
        $file = dirname(__DIR__, 2) . '/config/widgets.php';
        $secret = is_file($file) ? (string) ((require $file)['secret'] ?? '') : '';
        if ($secret === '') {
            return $responses[$userId] = ['status' => 0, 'viewer' => [], 'people' => [], 'groups' => []];
        }
        // Core runs on this server: the request goes straight to it.
        $core = \PlainShow\Kit\Platform::require('core');
        $host = (string) parse_url($core, PHP_URL_HOST);
        $url = $core.'/api/core/v1/groups/for-user?'.http_build_query(
            ['extension' => 'blog'] + Widgets::sign($userId, 'groups', $secret),
        );
        $connection = curl_init($url);
        curl_setopt_array($connection, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_RESOLVE => ["$host:443:127.0.0.1"],
        ]);
        $body = curl_exec($connection);
        $status = curl_getinfo($connection, CURLINFO_HTTP_CODE);
        curl_close($connection);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($status === 200 && (!is_array($decoded) || !is_array($decoded['viewer'] ?? null) || !is_array($decoded['people'] ?? null))) {
            $status = 0;
        }

        return $responses[$userId] = [
            'status' => $status,
            'viewer' => $status === 200 ? $decoded['viewer'] : [],
            'people' => $status === 200 ? $decoded['people'] : [],
            'groups' => $status === 200 ? ($decoded['groups'] ?? []) : [],
        ];
    }
}
