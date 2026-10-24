<?php

namespace Blog\Domain;

/**
 * Sends a stored picture by file name only (never a path), with caching
 * headers. `?w=` asks for a smaller copy, made once and kept in app/cache.
 */
final class ImageServer
{
    public const WIDTHS = [480, 960, 1600];
    private const TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** @param list<string> $directories */
    public static function send(array $directories, string $requested, int $width = 0): never
    {
        $name = basename($requested);
        if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            self::missing();
        }
        $path = '';
        foreach ($directories as $directory) {
            $candidate = rtrim($directory, '/') . '/' . $name;
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }
        $info = $path !== '' ? @getimagesize($path) : false;
        if (!$info || !in_array($info['mime'], self::TYPES, true)) {
            self::missing();
        }
        $mime = $info['mime'];
        if (in_array($width, self::WIDTHS, true) && $info[0] > $width && $mime !== 'image/gif') {
            $thumb = self::thumbnail($path, $name, $width);
            if ($thumb !== null) {
                [$path, $mime] = [$thumb, 'image/webp'];
            }
        }

        $modified = (int) filemtime($path);
        $etag = '"' . substr(sha1($path . '|' . filesize($path) . '|' . $modified), 0, 20) . '"';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        // File names are unique and never reused, so copies can be kept for good.
        header('Cache-Control: private, max-age=31536000, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }
        readfile($path);
        exit;
    }

    private static function thumbnail(string $source, string $name, int $width): ?string
    {
        $directory = dirname(__DIR__, 2) . '/cache/thumbs/' . $width;
        $target = $directory . '/' . preg_replace('/\.\w+$/', '', $name) . '.webp';
        if (is_file($target) && filemtime($target) >= filemtime($source)) {
            return $target;
        }
        if (!class_exists(\Imagick::class) || (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory))) {
            return null;
        }
        try {
            $image = new \Imagick($source);
            $image->autoOrient();
            $image->thumbnailImage($width, 0);
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(80);
            $temporary = $target . '.' . bin2hex(random_bytes(4));
            $image->writeImage($temporary);
            $image->clear();
            rename($temporary, $target);
            return $target;
        } catch (\Throwable $error) {
            error_log('Blog thumbnail ' . $name . ': ' . $error->getMessage());
            return null;
        }
    }

    private static function missing(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo ps_t('Bilden finns inte.');
        exit;
    }
}
