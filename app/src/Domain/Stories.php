<?php

namespace Blog\Domain;

use Blog\Api\ApiException;
use PlainShow\Kit\Identity;

/** Stories last 24 hours. The bar shows one ring per person. */
final class Stories
{
    private const ACTIVE = 'NOW() < DATE_ADD(s.created_at, INTERVAL 24 HOUR)';

    /** People with active stories, the viewer first, each with whether the viewer has seen them all. */
    public static function bar(int $viewerId): array
    {
        $conn = wocDb();
        $stmt = $conn->prepare('
            SELECT s.user_id, u.firstname, u.lastname, u.username, u.image_source,
                   COUNT(*) AS active, MAX(s.created_at) AS latest,
                   SUM(EXISTS(SELECT 1 FROM story_views v WHERE v.story_id = s.story_id AND v.viewer_id = ?)) AS seen
            FROM stories s JOIN `user` u ON u.user_id = s.user_id
            WHERE ' . self::ACTIVE . '
            GROUP BY s.user_id, u.firstname, u.lastname, u.username, u.image_source
            ORDER BY latest DESC');
        $stmt->bind_param('i', $viewerId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        usort($rows, fn ($a, $b) => ((int) $b['user_id'] === $viewerId) <=> ((int) $a['user_id'] === $viewerId));
        return $rows;
    }

    public static function forUser(int $userId, int $viewerId): array
    {
        $stmt = wocDb()->prepare('
            SELECT s.story_id, s.user_id, s.story_image, s.caption, s.created_at,
                   (SELECT COUNT(*) FROM story_views v WHERE v.story_id = s.story_id) AS views,
                   EXISTS(SELECT 1 FROM story_views v WHERE v.story_id = s.story_id AND v.viewer_id = ?) AS seen
            FROM stories s WHERE s.user_id = ? AND ' . self::ACTIVE . ' ORDER BY s.created_at');
        $stmt->bind_param('ii', $viewerId, $userId);
        $stmt->execute();
        return array_map(fn ($row) => [
            'id' => (int) $row['story_id'],
            'image' => 'image?file=' . rawurlencode((string) $row['story_image']),
            'caption' => (string) ($row['caption'] ?? ''),
            'at' => $row['created_at'],
            'ago' => blog_ago($row['created_at']),
            'views' => $userId === $viewerId ? (int) $row['views'] : null,
            'seen' => (bool) $row['seen'],
        ], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    public static function markViewed(int $storyId, int $viewerId): void
    {
        $stmt = wocDb()->prepare('INSERT IGNORE INTO story_views (story_id, viewer_id, viewed_at) SELECT story_id, ?, NOW() FROM stories WHERE story_id = ?');
        $stmt->bind_param('ii', $viewerId, $storyId);
        $stmt->execute();
    }

    public static function create(Identity $identity, array $file, string $caption): int
    {
        $name = Images::store($file, 1920, 78, 'story_');
        $userId = $identity->id();
        $stmt = wocDb()->prepare('INSERT INTO stories (user_id, story_image, caption, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->bind_param('iss', $userId, $name, $caption);
        $stmt->execute();
        $id = (int) wocDb()->insert_id;
        return $id;
    }

    public static function delete(int $storyId, Identity $identity): void
    {
        $conn = wocDb();
        $stmt = $conn->prepare('SELECT user_id, story_image FROM stories WHERE story_id = ?');
        $stmt->bind_param('i', $storyId);
        $stmt->execute();
        $story = $stmt->get_result()->fetch_assoc();
        if (!$story) {
            throw new ApiException(ps_t('Storyn finns inte.'), 404);
        }
        if ((int) $story['user_id'] !== $identity->id() && !woc_can($identity, 'blog.post.moderate')) {
            throw new ApiException(ps_t('Du kan bara ta bort dina egna stories.'), 403);
        }
        foreach (['DELETE FROM story_views WHERE story_id = ?', 'DELETE FROM stories WHERE story_id = ?'] as $sql) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $storyId);
            $stmt->execute();
        }
        Images::delete($story['story_image']);
    }
}
