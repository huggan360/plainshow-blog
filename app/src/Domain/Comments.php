<?php

namespace Blog\Domain;

use Blog\Api\ApiException;
use PlainShow\Kit\Identity;

/**
 * Post comments. The table keeps its original meaning: `comment_id` is the
 * post owner (who gets notified) and `rand_id` ties the comment to that
 * notification so both disappear together.
 */
final class Comments
{
    public static function forPost(int $postId): array
    {
        $stmt = wocDb()->prepare('
            SELECT c.id, c.blog_id, c.user_id, c.comment, c.rand_id, u.username, u.firstname, u.lastname, u.image_source, u.access_level, c.user_name
            FROM blog_comments c LEFT JOIN `user` u ON u.user_id = c.user_id
            WHERE c.blog_id = ? ORDER BY c.id');
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public static function add(int $postId, Identity $identity, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new ApiException(ps_t('Skriv något först.'), 422);
        }
        $post = Posts::find($postId, $identity->id());
        if (!$post) {
            throw new ApiException(ps_t('Inlägget finns inte.'), 404);
        }
        $owner = (int) $post['user'];
        $author = $identity->id();
        $name = $identity->name();
        $randId = random_int(100000, 999999);
        $stmt = wocDb()->prepare('INSERT INTO blog_comments (blog_id, comment_id, user_id, user_name, comment, rand_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiissi', $postId, $owner, $author, $name, $text, $randId);
        $stmt->execute();
        $id = (int) wocDb()->insert_id;
        if ($owner !== $author) {
            Notifier::record($owner, 'comment', $randId, "view_blog?post_id=$postId", "$name har kommenterat på ditt inlägg.");
        }
        foreach (self::forPost($postId) as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }
        throw new ApiException(ps_t('Kommentaren sparades inte.'), 500);
    }

    /**
     * A comment from a signed-out visitor, only on public posts. A hidden
     * field catches bots, and RateLimit keeps one visitor to a few comments.
     */
    public static function addGuest(int $postId, string $name, string $text, string $trap): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $text = trim($text);
        if ($trap !== '') {
            throw new ApiException(ps_t('Kommentaren kunde inte skickas.'), 422);
        }
        if (mb_strlen($name) < 2) {
            throw new ApiException(ps_t('Skriv ditt namn.'), 422);
        }
        if ($text === '') {
            throw new ApiException(ps_t('Skriv något först.'), 422);
        }
        $post = Posts::find($postId, 0);
        if (!$post || (int) $post['is_public'] !== 1) {
            throw new ApiException(ps_t('Inlägget finns inte.'), 404);
        }
        RateLimit::hit('guest-comment', 4, 600, ps_t('Du har skrivit många kommentarer på kort tid. Vänta en stund.'));
        $owner = (int) $post['user'];
        $guest = 0;
        $randId = random_int(100000, 999999);
        $stmt = wocDb()->prepare('INSERT INTO blog_comments (blog_id, comment_id, user_id, user_name, comment, rand_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiissi', $postId, $owner, $guest, $name, $text, $randId);
        $stmt->execute();
        $id = (int) wocDb()->insert_id;
        Notifier::record($owner, 'comment', $randId, "view_blog?post_id=$postId", "$name har kommenterat på ditt inlägg.");
        foreach (self::forPost($postId) as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }
        throw new ApiException(ps_t('Kommentaren sparades inte.'), 500);
    }

    /** Authors remove their own, post owners anything on their post, moderators anything. */
    public static function canDelete(?Identity $identity, array $comment, int $postOwner): bool
    {
        if ($identity === null) {
            return false;
        }
        $viewer = $identity->id();
        return ((int) $comment['user_id'] > 0 && (int) $comment['user_id'] === $viewer) || $postOwner === $viewer || woc_can($identity, 'blog.comment.moderate');
    }

    public static function delete(int $commentId, Identity $identity): void
    {
        $conn = wocDb();
        $stmt = $conn->prepare('SELECT c.user_id, c.rand_id, b.user AS post_owner FROM blog_comments c JOIN blog_data b ON b.id = c.blog_id WHERE c.id = ?');
        $stmt->bind_param('i', $commentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            throw new ApiException(ps_t('Kommentaren finns inte.'), 404);
        }
        $allowed = in_array($identity->id(), [(int) $row['user_id'], (int) $row['post_owner']], true) || woc_can($identity, 'blog.comment.moderate');
        if (!$allowed) {
            throw new ApiException(ps_t('Du kan bara ta bort dina egna kommentarer.'), 403);
        }
        $stmt = $conn->prepare('DELETE FROM blog_comments WHERE id = ?');
        $stmt->bind_param('i', $commentId);
        $stmt->execute();
        $randId = (int) $row['rand_id'];
        $stmt = $conn->prepare("DELETE FROM notiser WHERE assign_id = ? AND type = 'comment'");
        $stmt->bind_param('i', $randId);
        $stmt->execute();
    }
}
