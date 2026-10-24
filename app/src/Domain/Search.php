<?php

namespace Blog\Domain;

/** Search across people, posts and albums. Hidden albums only for album managers. */
final class Search
{
    public static function run(string $query, int $viewerId, bool $hiddenAlbums): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['people' => [], 'posts' => [], 'albums' => []];
        }
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $conn = wocDb();

        $stmt = $conn->prepare("SELECT user_id, username, firstname, lastname, image_source, access_level FROM `user`
            WHERE firstname LIKE ? OR lastname LIKE ? OR username LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?
            ORDER BY firstname, lastname LIMIT 12");
        $stmt->bind_param('ssss', $like, $like, $like, $like);
        $stmt->execute();
        $eligible = array_map('intval', array_column(platform_people(), 'id'));
        $people = array_values(array_filter($stmt->get_result()->fetch_all(MYSQLI_ASSOC), fn (array $person): bool => in_array((int) $person['user_id'], $eligible, true)));

        // Text of block posts lives in blog_sections, so search there too.
        $stmt = $conn->prepare("SELECT b.id, b.user, b.date, b.title, b.content, b.image, b.is_public, u.username, u.firstname, u.lastname, u.image_source
            FROM blog_data b JOIN `user` u ON u.user_id = b.user
            WHERE (b.title LIKE ? OR b.content LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ?
                   OR EXISTS (SELECT 1 FROM blog_sections s WHERE s.post_id = b.id AND s.section_type = 'text' AND s.content LIKE ?))
              AND (b.is_public = 1 OR ? > 0)
            ORDER BY b.title LIKE ? DESC, b.date DESC, b.id DESC LIMIT 30");
        $stmt->bind_param('ssssis', $like, $like, $like, $like, $viewerId, $like);
        $stmt->execute();
        $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $albums = array_values(array_filter(
            Galleries::listing(!$hiddenAlbums),
            fn ($album) => mb_stripos((string) $album['title'], $query) !== false && ($hiddenAlbums || (int) $album['published'] > 0),
        ));

        return ['people' => $people, 'posts' => $posts, 'albums' => array_slice($albums, 0, 8)];
    }
}
