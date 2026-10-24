<?php

namespace Blog\Domain;

use Blog\Api\ApiException;
use PlainShow\Kit\Identity;

/** Films shown on the home page. Stored under public/assets/vids, uploaded in chunks. */
final class Videos
{
    private const TYPES = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];
    private const MAX_BYTES = 1024 * 1024 * 1024;

    public static function directory(): string
    {
        return rtrim(getenv('BLOG_VIDEO_DIR') ?: dirname(__DIR__, 3) . '/public/assets/vids', '/');
    }

    public static function listing(bool $publicOnly): array
    {
        $where = $publicOnly ? 'WHERE v.is_public = 1' : '';
        return wocDb()->query("SELECT v.id, v.date, v.user_id, v.content, v.image, v.is_public, u.firstname, u.lastname, u.username, u.image_source
            FROM video_data v JOIN `user` u ON u.user_id = v.user_id $where ORDER BY v.id DESC")->fetch_all(MYSQLI_ASSOC);
    }

    /** One chunk of a film; the last one saves it and returns its id. */
    public static function receiveChunk(Identity $identity, array $meta, ?array $chunk, string $text, bool $public): ?int
    {
        $part = ChunkUpload::receive(self::directory(), $identity->id() . '|video', $meta, $chunk, self::MAX_BYTES);
        if ($part === null) {
            return null;
        }
        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($part);
        if (!isset(self::TYPES[$type])) {
            @unlink($part);
            throw new ApiException(ps_t('Filmen måste vara MP4, MOV eller WebM.'), 422);
        }
        $name = bin2hex(random_bytes(12)) . '.' . self::TYPES[$type];
        if (!rename($part, self::directory() . '/' . $name)) {
            @unlink($part);
            throw new ApiException(ps_t('Kunde inte spara filmen.'), 500);
        }
        @chmod(self::directory() . '/' . $name, 0664);
        $path = 'vids/' . $name;
        $date = date('Y-m-d');
        $userId = $identity->id();
        $flag = $public ? 1 : 0;
        $text = trim($text);
        $stmt = wocDb()->prepare('INSERT INTO video_data (date, user_id, content, image, is_public) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sissi', $date, $userId, $text, $path, $flag);
        $stmt->execute();
        return (int) wocDb()->insert_id;
    }

    public static function update(int $id, ?string $text, ?bool $public): void
    {
        self::find($id);
        if ($text !== null) {
            $text = trim($text);
            $stmt = wocDb()->prepare('UPDATE video_data SET content = ? WHERE id = ?');
            $stmt->bind_param('si', $text, $id);
            $stmt->execute();
        }
        if ($public !== null) {
            $flag = $public ? 1 : 0;
            $stmt = wocDb()->prepare('UPDATE video_data SET is_public = ? WHERE id = ?');
            $stmt->bind_param('ii', $flag, $id);
            $stmt->execute();
        }
    }

    public static function delete(int $id): void
    {
        $video = self::find($id);
        $stmt = wocDb()->prepare('DELETE FROM video_data WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $file = self::directory() . '/' . basename((string) $video['image']);
        if ($video['image'] && str_starts_with((string) $video['image'], 'vids/') && is_file($file)) {
            @unlink($file);
        }
    }

    private static function find(int $id): array
    {
        $stmt = wocDb()->prepare('SELECT id, image FROM video_data WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: throw new ApiException(ps_t('Filmen finns inte.'), 404);
    }
}
