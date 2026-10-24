<?php

namespace Blog\Domain;

use PlainShow\Kit\Identity;

/** The blog's admin area: who may see which page, and the data behind them. */
final class Admin
{
    /** Pages in the admin menu, with the capability each needs. */
    public const PAGES = [
        'posts' => ['Inlägg', 'bx-collection', '/admin', 'blog.post.moderate'],
        'videos' => ['Filmer', 'bx-movie-play', '/admin/videos', 'blog.post.moderate'],
        'notices' => ['Adminnotiser', 'bx-broadcast', '/admin/notices', 'blog.notice.moderate'],
        'analytics' => ['Statistik', 'bx-line-chart', '/admin/analytics', 'admin'],
    ];

    public static function can(?Identity $identity, string $page): bool
    {
        if ($identity === null) {
            return false;
        }
        $need = self::PAGES[$page][3];
        return $need === 'admin' ? woc_is_admin($identity) : woc_can($identity, $need);
    }

    public static function any(?Identity $identity): bool
    {
        foreach (array_keys(self::PAGES) as $page) {
            if (self::can($identity, $page)) {
                return true;
            }
        }
        return false;
    }

    /** All posts for moderation. $filter: all, public, hidden, today. */
    public static function posts(string $filter, string $search, int $page, int $perPage = 25): array
    {
        $where = ['1 = 1'];
        $types = '';
        $values = [];
        if ($filter === 'public') {
            $where[] = 'b.is_public = 1';
        } elseif ($filter === 'hidden') {
            $where[] = 'b.is_public = 0';
        } elseif ($filter === 'today') {
            $where[] = 'b.date = CURDATE()';
        }
        if ($search !== '') {
            $where[] = "(b.title LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ?)";
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $types .= 'ss';
            array_push($values, $like, $like);
        }
        $sql = 'FROM blog_data b JOIN `user` u ON u.user_id = b.user WHERE ' . implode(' AND ', $where);
        $stmt = wocDb()->prepare("SELECT COUNT(*) $sql");
        if ($values) {
            $stmt->bind_param($types, ...$values);
        }
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_row()[0];
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = wocDb()->prepare("SELECT b.id, b.user, b.date, b.title, b.image, b.is_public, u.firstname, u.lastname, u.username, u.image_source,
                (SELECT COUNT(*) FROM blog_comments c WHERE c.blog_id = b.id) AS comments,
                (SELECT COUNT(*) FROM likes l WHERE l.blog_id = b.id) AS likes
            $sql ORDER BY b.date DESC, b.id DESC LIMIT ? OFFSET ?");
        $stmt->bind_param($types . 'ii', ...[...$values, $perPage, $offset]);
        $stmt->execute();
        return ['rows' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function postCounts(): array
    {
        return wocDb()->query('SELECT COUNT(*) AS total, SUM(is_public = 1) AS public, SUM(is_public = 0) AS hidden, SUM(date = CURDATE()) AS today,
            (SELECT COUNT(*) FROM blog_comments) AS comments FROM blog_data')->fetch_assoc();
    }

    public static function setPublic(int $postId, bool $public): void
    {
        $flag = $public ? 1 : 0;
        $stmt = wocDb()->prepare('UPDATE blog_data SET is_public = ? WHERE id = ?');
        $stmt->bind_param('ii', $flag, $postId);
        $stmt->execute();
    }

    /* ---------- Admin notices ---------- */

    /** Sends one notice to each chosen member; one assign_id ties them together. */
    public static function sendNotice(array $userIds, string $message): int
    {
        $eligible = array_map('intval', array_column(platform_people(), 'id'));
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0 && in_array($id, $eligible, true))));
        $message = trim($message);
        if (!$ids || $message === '') {
            throw new \Blog\Api\ApiException(ps_t('Välj mottagare och skriv ett meddelande.'), 422);
        }
        $group = random_int(100000, 999999);
        $stmt = wocDb()->prepare("INSERT INTO notiser (user_id, type, assign_id, title, content, date) SELECT user_id, 'admin', ?, 'notiser', ?, NOW() FROM `user` WHERE user_id = ?");
        $sent = 0;
        foreach ($ids as $id) {
            $stmt->bind_param('isi', $group, $message, $id);
            $stmt->execute();
            $sent += $stmt->affected_rows;
        }
        return $sent;
    }

    /** Sent notices, one row per message with its recipients. */
    public static function notices(): array
    {
        return wocDb()->query("SELECT n.assign_id, MIN(n.content) AS content, MAX(n.date) AS date, COUNT(*) AS recipients,
                GROUP_CONCAT(TRIM(CONCAT(u.firstname, ' ', u.lastname)) ORDER BY u.firstname SEPARATOR ', ') AS names
            FROM notiser n LEFT JOIN `user` u ON u.user_id = n.user_id
            WHERE n.type = 'admin' GROUP BY n.assign_id ORDER BY date DESC")->fetch_all(MYSQLI_ASSOC);
    }

    public static function deleteNotice(int $group): void
    {
        $stmt = wocDb()->prepare("DELETE FROM notiser WHERE type = 'admin' AND assign_id = ?");
        $stmt->bind_param('i', $group);
        $stmt->execute();
    }

    public static function members(): array
    {
        return array_map(static fn (array $person): array => [
            'user_id' => (int) $person['id'],
            'username' => (string) ($person['username'] ?? ''),
            'firstname' => (string) ($person['firstName'] ?? ''),
            'lastname' => (string) ($person['lastName'] ?? ''),
            'image_source' => $person['avatar'] ?? '',
        ], platform_people());
    }

    /* ---------- Statistics (the old activity log) ---------- */

    public static function analytics(): array
    {
        $conn = wocDb();
        $summary = $conn->query("SELECT COUNT(*) AS loads, COUNT(DISTINCT user_id) AS people, COUNT(DISTINCT ip) AS ips, MIN(timestamp) AS first, MAX(timestamp) AS last,
            SUM(page = 'login.php') AS logins FROM user_activity_log")->fetch_assoc();
        $days = $conn->query("SELECT DATE_FORMAT(timestamp, '%Y-%m') AS month, COUNT(*) AS total FROM user_activity_log GROUP BY month ORDER BY month")->fetch_all(MYSQLI_ASSOC);
        $pages = $conn->query("SELECT REGEXP_REPLACE(REPLACE(page, '.php', ''), '[?].*$', '') AS page, COUNT(*) AS total, COUNT(DISTINCT user_id) AS people
            FROM user_activity_log WHERE page <> 'login.php' GROUP BY 1 ORDER BY total DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
        $people = $conn->query("SELECT l.user_id, u.firstname, u.lastname, u.username, u.image_source, COUNT(*) AS total, MAX(l.timestamp) AS last
            FROM user_activity_log l LEFT JOIN `user` u ON u.user_id = l.user_id GROUP BY l.user_id ORDER BY total DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
        return compact('summary', 'days', 'pages', 'people');
    }
}
