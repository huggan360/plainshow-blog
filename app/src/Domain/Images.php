<?php

namespace Blog\Domain;

use Blog\Api\ApiException;
use Imagick;
use Throwable;

/**
 * Every uploaded image is decoded and re-encoded as WebP, turned the right
 * way up and stripped of metadata (including GPS), so nothing else rides along.
 */
final class Images
{
    public const STORE = '/var/www/protected_pics';
    private const MAX_BYTES = 40 * 1024 * 1024;

    /** @return string the stored file name */
    public static function store(array $file, int $maxSide = 2400, int $quality = 80, string $prefix = 'img_'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new ApiException(self::uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)), 422);
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new ApiException(ps_t('Bilden är för stor (högst 40 MB).'), 413);
        }
        try {
            $image = new Imagick();
            $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 384 * 1024 * 1024);
            $image->readImage((string) $file['tmp_name']);
            $image = $image->coalesceImages()->getImage();
            $image->autoOrient();
            if (max($image->getImageWidth(), $image->getImageHeight()) > $maxSide) {
                $image->resizeImage($maxSide, $maxSide, Imagick::FILTER_LANCZOS, 1, true);
            }
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($quality);
            $name = $prefix . bin2hex(random_bytes(10)) . '.webp';
            if (!$image->writeImage(self::STORE . '/' . $name)) {
                throw new \RuntimeException('write failed');
            }
            $image->clear();
            @chmod(self::STORE . '/' . $name, 0664);
            return $name;
        } catch (ApiException $error) {
            throw $error;
        } catch (Throwable $error) {
            error_log('Image store failed: ' . $error->getMessage());
            throw new ApiException(ps_t('Filen kunde inte läsas som en bild.'), 422);
        }
    }

    public static function delete(?string $name): void
    {
        $name = basename((string) $name);
        if ($name !== '' && is_file(self::STORE . '/' . $name)) {
            @unlink(self::STORE . '/' . $name);
        }
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => ps_t('Filen är för stor.'),
            UPLOAD_ERR_PARTIAL => ps_t('Uppladdningen avbröts. Försök igen.'),
            UPLOAD_ERR_NO_FILE => ps_t('Välj en bild först.'),
            default => ps_t('Uppladdningen misslyckades.'),
        };
    }
}
