<?php

namespace Blog\Domain;

use Blog\Api\ApiException;

/**
 * Receives a large file in chunks. Each upload is tied to a scope (who and
 * where), so another session cannot add to it. Returns the assembled file
 * path after the last chunk, null before that. The caller removes the file.
 */
final class ChunkUpload
{
    public const CHUNK_BYTES = 4 * 1024 * 1024;

    /** @param array{upload_id: string, file_size: int, index: int, total: int} $meta */
    public static function receive(string $directory, string $scope, array $meta, ?array $chunk, int $maxBytes): ?string
    {
        $uploadId = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $meta['upload_id']) ?? '', 0, 64);
        $size = (int) $meta['file_size'];
        $index = (int) $meta['index'];
        $total = (int) $meta['total'];
        if (strlen($uploadId) < 8 || $size <= 0 || $total <= 0 || $index < 0 || $index >= $total || $total > (int) ceil($maxBytes / self::CHUNK_BYTES) + 1) {
            throw new ApiException(ps_t('Ogiltig uppladdning.'), 422);
        }
        if ($size > $maxBytes) {
            throw new ApiException(ps_t('Filen är för stor (högst ') . round($maxBytes / 1048576) . ' MB).', 413);
        }
        if (!$chunk || ($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($chunk['tmp_name'])) {
            throw new ApiException(ps_t('En del av filen kom inte fram. Försök igen.'), 422);
        }
        $directory = rtrim($directory, '/') . '/.chunks';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new ApiException(ps_t('Servern kan inte ta emot filer just nu.'), 500);
        }
        $part = $directory . '/' . hash('sha256', $uploadId . '|' . $scope) . '.part';
        if ($index === 0) {
            @unlink($part);
        } elseif (!is_file($part)) {
            throw new ApiException(ps_t('Uppladdningen avbröts. Försök igen.'), 409);
        }
        $in = fopen($chunk['tmp_name'], 'rb');
        $out = fopen($part, 'ab');
        if (!$in || !$out || stream_copy_to_stream($in, $out) === false) {
            @unlink($part);
            throw new ApiException(ps_t('Kunde inte spara filen.'), 500);
        }
        fclose($in);
        fclose($out);
        clearstatcache(true, $part);
        if (filesize($part) > $size) {
            @unlink($part);
            throw new ApiException(ps_t('Filen blev större än väntat. Försök igen.'), 422);
        }
        if ($index < $total - 1) {
            return null;
        }
        if (filesize($part) !== $size) {
            @unlink($part);
            throw new ApiException(ps_t('Filen kom inte fram hel. Försök igen.'), 422);
        }
        return $part;
    }
}
