<?php

namespace Blog\Domain;

/** Album listings. Uploading and editing use gallery-back.php. */
final class Galleries
{
    /** Albums with their cover photo and number of published photos, newest activity first. */
    public static function listing(bool $publicOnly, ?int $limit = null): array
    {
        $where = $publicOnly ? 'WHERE gl.is_public = 1' : '';
        $sql = "
            SELECT gl.gallery_id, gl.title, gl.is_public,
                   MAX(g.date) AS latest,
                   COUNT(CASE WHEN g.is_published = 1 THEN 1 END) AS published,
                   COUNT(g.image_id) AS total,
                   MIN(CASE WHEN g.is_published = 1 THEN g.image_source END) AS cover
            FROM galleries gl
            LEFT JOIN gallery g ON g.gallery_id = gl.gallery_id
            $where
            GROUP BY gl.gallery_id, gl.title, gl.is_public
            ORDER BY MAX(g.date) IS NULL, MAX(g.date) DESC, gl.gallery_id DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        return wocDb()->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public static function find(int $galleryId): ?array
    {
        $stmt = wocDb()->prepare('SELECT gallery_id, title, is_public FROM galleries WHERE gallery_id = ?');
        $stmt->bind_param('i', $galleryId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Photos in an album. Unpublished ones only for people who may edit it. */
    public static function photos(int $galleryId, bool $includeUnpublished): array
    {
        $sql = 'SELECT g.image_id, g.image_source, g.date, g.is_published, g.upload_id, u.firstname, u.lastname, u.username, u.image_source AS avatar
                FROM gallery g LEFT JOIN `user` u ON u.user_id = g.upload_id
                WHERE g.gallery_id = ?' . ($includeUnpublished ? '' : ' AND g.is_published = 1') . '
                ORDER BY g.date DESC, g.image_id DESC';
        $stmt = wocDb()->prepare($sql);
        $stmt->bind_param('i', $galleryId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
