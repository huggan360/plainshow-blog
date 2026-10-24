<?php

namespace Blog\Domain;

use Blog\Api\ApiException;
use PlainShow\Kit\Identity;

/**
 * Vinrating: wines with a picture, and one shared rating per wine, as the
 * original page had it. Moderators rate; admins add, edit and remove.
 */
final class Wines
{
    private const DIRECTORY = '/var/www/protected_pics';

    public static function all(string $search = ''): array
    {
        $sql = 'SELECT w.wine_id, w.image, w.title, w.description, r.rating, r.comment, r.created_at, u.firstname, u.lastname, u.username
                FROM wines w LEFT JOIN wine_ratings r ON r.wine_id = w.wine_id LEFT JOIN `user` u ON u.user_id = r.user_id';
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $stmt = wocDb()->prepare($sql . ' WHERE w.title LIKE ? OR w.description LIKE ? OR r.comment LIKE ? ORDER BY w.title');
            $stmt->bind_param('sss', $like, $like, $like);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        return wocDb()->query($sql . ' ORDER BY w.title')->fetch_all(MYSQLI_ASSOC);
    }

    public static function canRate(?Identity $identity): bool
    {
        return $identity !== null && woc_can($identity, 'blog.post.moderate');
    }

    public static function canManage(?Identity $identity): bool
    {
        return $identity !== null && woc_is_admin($identity);
    }

    public static function create(string $title, string $description, ?array $image): int
    {
        [$title, $description] = self::clean($title, $description);
        $path = $image ? self::storeImage($image) : '';
        $stmt = wocDb()->prepare('INSERT INTO wines (title, description, image) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $title, $description, $path);
        $stmt->execute();
        return (int) wocDb()->insert_id;
    }

    public static function update(int $wineId, string $title, string $description): void
    {
        [$title, $description] = self::clean($title, $description);
        $stmt = wocDb()->prepare('UPDATE wines SET title = ?, description = ? WHERE wine_id = ?');
        $stmt->bind_param('ssi', $title, $description, $wineId);
        $stmt->execute();
        if ($stmt->affected_rows === 0 && !self::find($wineId)) {
            throw new ApiException(ps_t('Vinet finns inte.'), 404);
        }
    }

    public static function delete(int $wineId): void
    {
        $wine = self::find($wineId) ?? throw new ApiException(ps_t('Vinet finns inte.'), 404);
        foreach (['DELETE FROM wine_ratings WHERE wine_id = ?', 'DELETE FROM wines WHERE wine_id = ?'] as $sql) {
            $stmt = wocDb()->prepare($sql);
            $stmt->bind_param('i', $wineId);
            $stmt->execute();
        }
        $file = self::DIRECTORY . '/' . basename((string) $wine['image']);
        if ($wine['image'] && str_starts_with(basename($file), 'wine_') && is_file($file)) {
            @unlink($file);
        }
    }

    public static function rate(int $wineId, Identity $identity, float $rating, string $comment): void
    {
        if (!self::find($wineId)) {
            throw new ApiException(ps_t('Vinet finns inte.'), 404);
        }
        if ($rating < 1 || $rating > 10) {
            throw new ApiException(ps_t('Betyget ska vara mellan 1 och 10.'), 422);
        }
        $rating = round($rating * 2) / 2;
        $userId = $identity->id();
        // One rating per wine, as before. INSERT IGNORE keeps it even if two people press at once.
        $stmt = wocDb()->prepare('INSERT INTO wine_ratings (wine_id, user_id, rating, comment) SELECT ?, ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM wine_ratings WHERE wine_id = ?)');
        $comment = trim($comment);
        $stmt->bind_param('iidsi', $wineId, $userId, $rating, $comment, $wineId);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new ApiException(ps_t('Detta vin är redan betygsatt!'), 409);
        }
    }

    private static function find(int $wineId): ?array
    {
        $stmt = wocDb()->prepare('SELECT wine_id, image FROM wines WHERE wine_id = ?');
        $stmt->bind_param('i', $wineId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private static function clean(string $title, string $description): array
    {
        $title = trim($title);
        $description = trim($description);
        if ($title === '' || mb_strlen($title) > 190) {
            throw new ApiException(ps_t('Skriv vinets namn.'), 422);
        }
        return [$title, mb_substr($description, 0, 2000)];
    }

    private static function storeImage(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new ApiException(ps_t('Bilden kom inte fram.'), 422);
        }
        try {
            $image = new \Imagick($file['tmp_name']);
            $image->autoOrient();
            if ($image->getImageWidth() > 1600 || $image->getImageHeight() > 1600) {
                $image->thumbnailImage(1600, 1600, true);
            }
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(80);
            $path = self::DIRECTORY . '/' . uniqid('wine_', true) . '.webp';
            $image->writeImage($path);
            $image->clear();
            return $path;
        } catch (\Throwable) {
            throw new ApiException(ps_t('Bilden kunde inte läsas.'), 422);
        }
    }
}
