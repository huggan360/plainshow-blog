<?php

namespace Blog\Domain;

use PlainShow\Kit\Identity;

/** Reading posts, likes and the editing rule. Creating and updating use posts-back.php. */
final class Posts
{
    private const SELECT = '
        SELECT b.id, b.user, b.date, b.title, b.content, b.image, b.is_public,
               u.username, u.firstname, u.lastname, u.image_source,
               (SELECT COUNT(*) FROM likes l WHERE l.blog_id = b.id) AS likes,
               EXISTS(SELECT 1 FROM likes l WHERE l.blog_id = b.id AND l.user_id = ?) AS liked,
               (SELECT COUNT(*) FROM blog_comments c WHERE c.blog_id = b.id) AS comments
        FROM blog_data b
        JOIN `user` u ON u.user_id = b.user';

    /** Newest first. Signed-out visitors only see public posts. */
    public static function feed(int $viewerId, int $limit = 10, int $offset = 0): array
    {
        $public = $viewerId > 0 ? '' : ' WHERE b.is_public = 1';
        $stmt = wocDb()->prepare(self::SELECT . $public . ' ORDER BY b.date DESC, b.id DESC LIMIT ? OFFSET ?');
        $stmt->bind_param('iii', $viewerId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public static function byAuthor(int $authorId, int $viewerId): array
    {
        $stmt = wocDb()->prepare(self::SELECT . ' WHERE b.user = ? ORDER BY b.date DESC, b.id DESC');
        $stmt->bind_param('ii', $viewerId, $authorId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public static function find(int $postId, int $viewerId): ?array
    {
        $stmt = wocDb()->prepare(self::SELECT . ' WHERE b.id = ?');
        $stmt->bind_param('ii', $viewerId, $postId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Who liked a post, oldest like first. */
    public static function likers(int $postId): array
    {
        $stmt = wocDb()->prepare('SELECT u.user_id, u.username, u.firstname, u.lastname, u.image_source FROM likes l JOIN `user` u ON u.user_id = l.user_id WHERE l.blog_id = ? ORDER BY l.id');
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** The post's body blocks in order: text and images. */
    public static function blocks(int $postId): array
    {
        $conn = wocDb();
        $stmt = $conn->prepare('SELECT id, section_type, content FROM blog_sections WHERE post_id = ? ORDER BY id');
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt = $conn->prepare('SELECT section_id, image_path FROM blog_images WHERE post_id = ? ORDER BY id');
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $images = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $images[(int) $row['section_id']][] = $row['image_path'];
        }
        $blocks = [];
        foreach ($sections as $section) {
            if ($section['section_type'] === 'image') {
                foreach ($images[(int) $section['id']] ?? [$section['content']] as $path) {
                    if ($path !== '') {
                        $blocks[] = ['type' => 'image', 'path' => $path];
                    }
                }
            } elseif (trim((string) $section['content']) !== '') {
                $blocks[] = ['type' => 'text', 'content' => (string) $section['content']];
            }
        }
        return $blocks;
    }

    public static function canEdit(?Identity $identity, array $post): bool
    {
        return $identity !== null && ($identity->id() === (int) $post['user'] || woc_can($identity, 'blog.post.moderate'));
    }

    /** @return array{liked: bool, likes: int} */
    public static function setLike(int $postId, Identity $identity, bool $like): array
    {
        $conn = wocDb();
        $userId = $identity->id();
        $post = self::find($postId, $userId);
        if (!$post) {
            throw new \Blog\Api\ApiException(ps_t('Inlägget finns inte.'), 404);
        }
        if ($like) {
            $stmt = $conn->prepare('INSERT IGNORE INTO likes (user_id, blog_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $userId, $postId);
            $stmt->execute();
            $isNew = $stmt->affected_rows > 0;
            $owner = (int) $post['user'];
            if ($isNew && $owner !== $userId) {
                $name = $identity->name();
                Notifier::record($owner, 'like', $postId, "view_blog?post_id=$postId", "$name har gillat ditt inlägg");
            }
        } else {
            $stmt = $conn->prepare('DELETE FROM likes WHERE user_id = ? AND blog_id = ?');
            $stmt->bind_param('ii', $userId, $postId);
            $stmt->execute();
        }
        $fresh = self::find($postId, $userId);
        return ['liked' => (bool) $fresh['liked'], 'likes' => (int) $fresh['likes']];
    }
}
