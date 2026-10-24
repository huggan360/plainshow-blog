<?php

namespace Blog\Domain;

use Blog\Api\ApiException;

/** A member's notices (the notiser table): likes, comments, follows, Split and admin notices. */
final class Notifications
{
    private const ICONS = ['like' => 'bxs-heart', 'comment' => 'bxs-comment-detail', 'follow' => 'bxs-user-plus', 'unfollow' => 'bx-user-minus', 'split' => 'bxs-wallet', 'admin' => 'bxs-megaphone'];

    /** Admin notices first, then newest first. */
    public static function forUser(int $userId, int $limit = 200): array
    {
        $stmt = wocDb()->prepare("SELECT id, type, title AS link, content, date FROM notiser WHERE user_id = ? ORDER BY type = 'admin' DESC, date DESC, id DESC LIMIT ?");
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        return array_map(fn (array $row) => $row + ['href' => self::href((string) $row['link']), 'icon' => self::ICONS[$row['type']] ?? 'bxs-bell'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public static function count(int $userId): int
    {
        $stmt = wocDb()->prepare('SELECT COUNT(*) FROM notiser WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_row()[0];
    }

    public static function delete(int $userId, int $id): void
    {
        $stmt = wocDb()->prepare("DELETE FROM notiser WHERE id = ? AND user_id = ? AND type <> 'admin'");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new ApiException(ps_t('Notisen finns inte.'), 404);
        }
    }

    /** Clears everything except admin notices, which an admin removes. */
    public static function clear(int $userId): int
    {
        $stmt = wocDb()->prepare("DELETE FROM notiser WHERE user_id = ? AND type <> 'admin'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    /** Stored links are relative blog paths; anything else is dropped. */
    public static function href(string $link): ?string
    {
        if (preg_match('~^https://split\.plainshow\.se/[\w\-./?=&%]*$~', trim($link))) {
            return trim($link);
        }
        $link = ltrim(trim($link), '/');
        if ($link === '' || !preg_match('~^(view_blog|profile|view_gallery|gallery|blogfeed|notiser|split)(\.php)?([/?][\w\-./?=&%]*)?$~', $link)) {
            return null;
        }
        if (str_starts_with($link, 'split')) {
            return '/' . $link;
        }
        return '/' . preg_replace('~^(\w+)\.php~', '$1', $link);
    }
}
