<?php

namespace Blog\Domain;

use Blog\Api\ApiException;
use PlainShow\Kit\Identity;

/**
 * Albums and their photos. Uploads arrive in chunks, are checked, turned
 * upright and re-encoded to WebP by gallery-back.php, and wait unpublished
 * until someone who manages albums publishes them.
 */
final class Albums
{
    public const CHUNK_BYTES = ChunkUpload::CHUNK_BYTES;

    /** Where album photos live. BLOG_ALBUM_DIR points a dev server elsewhere. */
    public static function directory(): string
    {
        return rtrim(getenv('BLOG_ALBUM_DIR') ?: '/var/www/photo_album', '/');
    }

    /** Where photos are read from: the upload directory and the original one. */
    public static function readDirectories(): array
    {
        return array_values(array_unique([self::directory(), '/var/www/photo_album']));
    }

    public static function create(string $title, bool $public): int
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 190) {
            throw new ApiException(ps_t('Ge albumet ett namn (högst 190 tecken).'), 422);
        }
        $flag = $public ? 1 : 0;
        $stmt = wocDb()->prepare('INSERT INTO galleries (title, is_public) VALUES (?, ?)');
        $stmt->bind_param('si', $title, $flag);
        $stmt->execute();
        return (int) wocDb()->insert_id;
    }

    public static function update(int $albumId, ?string $title, ?bool $public): void
    {
        self::require($albumId);
        if ($title !== null) {
            $title = trim($title);
            if ($title === '' || mb_strlen($title) > 190) {
                throw new ApiException(ps_t('Ge albumet ett namn (högst 190 tecken).'), 422);
            }
            $stmt = wocDb()->prepare('UPDATE galleries SET title = ? WHERE gallery_id = ?');
            $stmt->bind_param('si', $title, $albumId);
            $stmt->execute();
        }
        if ($public !== null) {
            $flag = $public ? 1 : 0;
            $stmt = wocDb()->prepare('UPDATE galleries SET is_public = ? WHERE gallery_id = ?');
            $stmt->bind_param('ii', $flag, $albumId);
            $stmt->execute();
        }
    }

    public static function delete(int $albumId): void
    {
        self::require($albumId);
        $files = self::sources('SELECT image_source FROM gallery WHERE gallery_id = ?', $albumId);
        $conn = wocDb();
        $conn->begin_transaction();
        try {
            foreach (['DELETE FROM gallery WHERE gallery_id = ?', 'DELETE FROM galleries WHERE gallery_id = ?'] as $sql) {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $albumId);
                $stmt->execute();
            }
            $conn->commit();
        } catch (\Throwable $error) {
            $conn->rollback();
            throw $error;
        }
        array_walk($files, [self::class, 'removeFile']);
    }

    /** Publish or hide photos in one album. Returns how many changed. */
    public static function publish(int $albumId, array $photoIds, bool $published): int
    {
        self::require($albumId);
        $ids = array_values(array_unique(array_filter(array_map('intval', $photoIds), fn ($id) => $id > 0)));
        if (!$ids) {
            return 0;
        }
        $flag = $published ? 1 : 0;
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = wocDb()->prepare("UPDATE gallery SET is_published = ? WHERE gallery_id = ? AND image_id IN ($marks)");
        $stmt->bind_param('ii' . str_repeat('i', count($ids)), $flag, $albumId, ...$ids);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    /** Managers remove any photo; uploaders remove their own until it is published. */
    public static function deletePhotos(int $albumId, array $photoIds, Identity $identity): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $photoIds), fn ($id) => $id > 0)));
        if (!$ids) {
            return 0;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = wocDb()->prepare("SELECT image_id, image_source, upload_id, is_published FROM gallery WHERE gallery_id = ? AND image_id IN ($marks)");
        $stmt->bind_param('i' . str_repeat('i', count($ids)), $albumId, ...$ids);
        $stmt->execute();
        $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($photos as $photo) {
            $own = (int) $photo['upload_id'] === $identity->id() && !(int) $photo['is_published'];
            if (!$own && !woc_can($identity, 'blog.gallery.moderate')) {
                throw new ApiException(ps_t('Du kan bara ta bort dina egna opublicerade bilder.'), 403);
            }
        }
        $removed = 0;
        foreach ($photos as $photo) {
            $delete = wocDb()->prepare('DELETE FROM gallery WHERE image_id = ?');
            $id = (int) $photo['image_id'];
            $delete->bind_param('i', $id);
            $delete->execute();
            self::removeFile($photo['image_source']);
            $removed++;
        }
        return $removed;
    }

    /**
     * One chunk of an upload. The last chunk turns the assembled file into a
     * photo and returns it; earlier chunks return null.
     *
     * @param array{upload_id: string, file_name: string, file_size: int, index: int, total: int} $meta
     */
    public static function receiveChunk(int $albumId, Identity $identity, array $meta, ?array $chunk): ?array
    {
        $album = Galleries::find($albumId);
        if (!$album || (!(int) $album['is_public'] && !woc_can($identity, 'blog.gallery.moderate'))) {
            throw new ApiException(ps_t('Albumet finns inte.'), 404);
        }
        $part = ChunkUpload::receive(self::directory(), $identity->id() . '|album|' . $albumId, $meta, $chunk, 50 * 1024 * 1024);
        if ($part === null) {
            return null;
        }
        $size = (int) filesize($part);

        if (!defined('GALLERY_STORAGE_DIR')) {
            define('GALLERY_STORAGE_DIR', self::directory() . '/');
        }
        $conn = wocDb();
        require_once dirname(__DIR__, 2) . '/backend/gallery-back.php';
        $result = galleryUploadImageInternal($conn, $albumId, $identity->id(), ['name' => basename($meta['file_name']), 'tmp_name' => $part, 'error' => UPLOAD_ERR_OK, 'size' => $size], true);
        @unlink($part);
        if (!$result['ok']) {
            throw new ApiException(ps_t('Bilden kunde inte läsas. Använd JPEG, PNG, GIF eller WebP.'), 422);
        }
        return ['id' => (int) $result['image_id'], 'url' => gallery_image_url($result['image_source'], 480), 'full' => gallery_image_url($result['image_source'])];
    }

    private static function require(int $albumId): void
    {
        if (!Galleries::find($albumId)) {
            throw new ApiException(ps_t('Albumet finns inte.'), 404);
        }
    }

    private static function sources(string $sql, int $id): array
    {
        $stmt = wocDb()->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return array_column($stmt->get_result()->fetch_all(), 0);
    }

    /** Only files inside the album directory are ever removed. */
    private static function removeFile(string $source): void
    {
        $file = self::directory() . '/' . basename($source);
        if (realpath(dirname($file)) === realpath(self::directory()) && is_file($file)) {
            @unlink($file);
        }
    }
}
