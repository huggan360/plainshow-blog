<?php

namespace Blog\Domain;

use Blog\Api\ApiException;
use PlainShow\Kit\KitException;
use PlainShow\Kit\Widgets;

/** The blog's widgets on the PlainShow dashboard. Core signs each request (PlainShow\Kit\Widgets). */
final class DashboardWidgets
{
    public static function handle(string $widget): array
    {
        $file = dirname(__DIR__, 3) . '/config/widgets.php';
        try {
            $userId = Widgets::verify($_GET, $widget, is_file($file) ? (string) (require $file)['secret'] : '');
        } catch (KitException $error) {
            throw new ApiException($error->getMessage(), 403);
        }
        return match ($widget) {
            'latest-posts' => self::latestPosts($userId),
            'new-photos' => self::newPhotos(),
        };
    }

    private static function latestPosts(int $userId): array
    {
        $items = array_map(fn (array $post) => [
            'title' => $post['title'],
            'meta' => member_name($post) . ', ' . blog_date($post['date']),
            'url' => ps_url('blog') . '/view_blog?post_id=' . (int) $post['id'],
            'image' => $post['image'] ? ps_url('blog') . post_image_url($post['image'], 480) : null,
            'avatar' => !$post['image'] && str_starts_with((string) $post['image_source'], 'https://') ? $post['image_source'] : null,
            'initials' => mb_strtoupper(mb_substr((string) $post['firstname'], 0, 1) . mb_substr((string) $post['lastname'], 0, 1)),
        ], Posts::feed($userId, 5));
        return ['type' => 'list', 'items' => $items, 'empty' => ps_t('Inga inlägg ännu.'), 'more' => ['label' => ps_t('Alla inlägg'), 'url' => ps_url('blog') . '/blogfeed']];
    }

    private static function newPhotos(): array
    {
        $rows = wocDb()->query('SELECT g.image_source, g.gallery_id FROM gallery g JOIN galleries gl ON gl.gallery_id = g.gallery_id
            WHERE g.is_published = 1 AND gl.is_public = 1 ORDER BY g.date DESC, g.image_id DESC LIMIT 9')->fetch_all(MYSQLI_ASSOC);
        return [
            'type' => 'gallery',
            'images' => array_map(fn (array $row) => [
                'src' => ps_url('blog') . gallery_image_url($row['image_source'], 480),
                'url' => ps_url('blog') . '/view_gallery?gallery_id=' . (int) $row['gallery_id'],
            ], $rows),
            'empty' => ps_t('Inga bilder ännu.'),
            'more' => ['label' => ps_t('Alla album'), 'url' => ps_url('blog') . '/gallery'],
        ];
    }
}
