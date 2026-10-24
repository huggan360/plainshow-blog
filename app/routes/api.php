<?php
/*
 * Blog API, version 1: https://blog.plainshow.se/api/v1/...
 * Also reachable at https://plainshow.se/api/blog/v1/... through the gateway.
 */

use Blog\Api\ApiException;
use Blog\Api\Request;
use Blog\Api\Router;
use Blog\Domain\Albums;
use Blog\Domain\Comments;
use Blog\Domain\Posts;
use Blog\Domain\Wines;

/** @var Router $router */

$router->add('GET', '/health', fn () => ['service' => 'blog', 'version' => json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/plainshow.json'), true)['version'] ?? null], null);

// Feed, as rendered cards for infinite scroll.
$router->add('GET', '/posts', function (Request $request) {
    $offset = max(0, $request->int('offset'));
    $limit = min(20, max(1, $request->int('limit', 10)));
    $posts = Posts::feed(current_identity()->id(), $limit + 1, $offset);
    $more = count($posts) > $limit;
    ob_start();
    foreach (array_slice($posts, 0, $limit) as $post) {
        include dirname(__DIR__) . '/views/partials/post-card.php';
    }
    return ['html' => ob_get_clean(), 'next' => $more ? $offset + $limit : null];
}, 'blog.post.view');

$router->add('POST', '/posts/{id}/like', fn (Request $r) => Posts::setLike($r->param('id'), current_identity(), true), 'blog.post.view');
$router->add('DELETE', '/posts/{id}/like', fn (Request $r) => Posts::setLike($r->param('id'), current_identity(), false), 'blog.post.view');

/*
 * Writing and editing posts. The form sends title, is_public, sections_json
 * (the blocks in order) and the new pictures as uploads[key]. Pictures are
 * checked, turned upright and re-encoded to WebP by posts-back.php.
 * Only moderators can make a post public, as before.
 */
$router->add('POST', '/posts', function () {
    $identity = current_identity();
    $conn = wocDb();
    require_once dirname(__DIR__) . '/backend/posts-back.php';
    if (!woc_can($identity, 'blog.post.moderate')) {
        $_POST['is_public'] = '0';
    }
    $result = postsCreatePost($conn, ['user_id' => $identity->id(), 'firstname' => $identity->firstName(), 'lastname' => $identity->lastName()]);
    if (!$result['ok']) {
        throw new ApiException($result['message'], 422);
    }
    $postId = (int) $result['post_id'];
    return ['post_id' => $postId, 'redirect' => "/view_blog?post_id=$postId"];
}, 'blog.post.create');

$router->add('POST', '/posts/{id}', function (Request $request) {
    $identity = current_identity();
    $post = Posts::find($request->param('id'), $identity->id());
    if (!$post) {
        throw new ApiException('Inlägget finns inte.', 404);
    }
    if (!Posts::canEdit($identity, $post)) {
        throw new ApiException('Du kan bara redigera dina egna inlägg.', 403);
    }
    $conn = wocDb();
    require_once dirname(__DIR__) . '/backend/posts-back.php';
    $title = postsNormalizeTitle($_POST['title'] ?? '');
    if ($title === '') {
        throw new ApiException('Titel krävs.', 422);
    }
    $isPublic = woc_can($identity, 'blog.post.moderate') ? postsNormalizePublicFlag($_POST['is_public'] ?? 0) : (int) $post['is_public'];
    // The editor refers to kept pictures by their index in the list the page was given.
    $known = $_SESSION['blog_editor'][(int) $post['id']] ?? null;
    $sections = json_decode((string) ($_POST['sections_json'] ?? ''), true);
    if (!is_array($known) || !is_array($sections)) {
        throw new ApiException('Sidan har varit öppen länge. Ladda om och försök igen.', 419);
    }
    foreach ($sections as &$section) {
        if (is_array($section) && ($section['source'] ?? '') === 'existing') {
            $section['image_path'] = $known[(int) ($section['existing'] ?? -1)] ?? '';
        }
    }
    unset($section);
    $_POST['sections_json'] = json_encode($sections);
    $blocks = postsParseBlocksFromRequest();
    if (!$blocks['ok'] || !$blocks['structured']) {
        throw new ApiException($blocks['message'] ?: 'Ogiltig blockdata.', 422);
    }
    $result = postsUpdateStructuredPost($conn, (int) $post['id'], $title, $isPublic, $blocks);
    if (!$result['ok']) {
        throw new ApiException($result['message'], 422);
    }
    return ['post_id' => (int) $post['id'], 'redirect' => '/view_blog?post_id=' . (int) $post['id']];
}, 'blog.post.view');

// Deleting a post: its author or a moderator. Images no other post uses go too.
$router->add('DELETE', '/posts/{id}', function (Request $request) {
    $identity = current_identity();
    $post = Posts::find($request->param('id'), $identity->id());
    if (!$post) {
        throw new ApiException('Inlägget finns inte.', 404);
    }
    if (!Posts::canEdit($identity, $post)) {
        throw new ApiException('Du kan bara ta bort dina egna inlägg.', 403);
    }
    $conn = wocDb();
    require_once dirname(__DIR__) . '/backend/posts-back.php';
    $result = postsDeletePost($conn, (int) $post['id']);
    if (!$result['ok']) {
        throw new ApiException($result['message'], 500);
    }
    return ['redirect' => '/blogfeed'];
}, 'blog.post.view');

$router->add('GET', '/posts/{id}/likes', function (Request $request) {
    return ['people' => array_map(fn ($person) => [
        'id' => (int) $person['user_id'],
        'name' => member_name($person),
        'avatar' => avatar($person, 'sm'),
    ], Posts::likers($request->param('id')))];
}, 'blog.post.view');

$router->add('POST', '/posts/{id}/comments', function (Request $request) {
    $comment = Comments::add($request->param('id'), current_identity(), $request->string('comment', 4000));
    $canDelete = true;
    ob_start();
    include dirname(__DIR__) . '/views/partials/comment.php';
    return ['html' => ob_get_clean(), 'id' => (int) $comment['id']];
}, 'blog.comment.create');
// Signed-out visitors can comment on public posts.
$router->add('POST', '/posts/{id}/guest-comments', function (Request $request) {
    if (current_identity() !== null) {
        throw new ApiException('Du är redan inloggad.', 409);
    }
    $comment = Comments::addGuest($request->param('id'), $request->string('name', 60), $request->string('comment', 2000), (string) $request->input('website', ''));
    $canDelete = false;
    ob_start();
    include dirname(__DIR__) . '/views/partials/comment.php';
    return ['html' => ob_get_clean(), 'id' => (int) $comment['id']];
}, null);
$router->add('DELETE', '/comments/{id}', function (Request $request) {
    Comments::delete($request->param('id'), current_identity());
    return [];
}, 'blog.post.view');

// Stories.
$router->add('GET', '/stories/users/{id}', fn (Request $r) => ['stories' => Blog\Domain\Stories::forUser($r->param('id'), current_identity()->id())], 'blog.post.view');
$router->add('POST', '/stories', function (Request $request) {
    $file = $request->file('image');
    if (!$file) {
        throw new ApiException('Välj en bild först.', 422);
    }
    return ['id' => Blog\Domain\Stories::create(current_identity(), $file, $request->string('caption', 300))];
}, 'blog.story.create');
$router->add('POST', '/stories/{id}/view', function (Request $request) {
    Blog\Domain\Stories::markViewed($request->param('id'), current_identity()->id());
    return [];
}, 'blog.post.view');
$router->add('DELETE', '/stories/{id}', function (Request $request) {
    Blog\Domain\Stories::delete($request->param('id'), current_identity());
    return [];
}, 'blog.post.view');

// Notifications.
$router->add('DELETE', '/notifications/{id}', function (Request $request) {
    Blog\Domain\Notifications::delete(current_identity()->id(), $request->param('id'));
    return ['count' => Blog\Domain\Notifications::count(current_identity()->id())];
}, 'blog.post.view');
$router->add('DELETE', '/notifications', function () {
    Blog\Domain\Notifications::clear(current_identity()->id());
    return ['count' => Blog\Domain\Notifications::count(current_identity()->id())];
}, 'blog.post.view');

// Albums. Managing albums and publishing photos needs blog.gallery.moderate;
// uploading needs blog.gallery.create, and new photos wait unpublished.
$router->add('POST', '/albums', function (Request $request) {
    $id = Albums::create($request->string('title', 190), (bool) $request->int('is_public'));
    return ['id' => $id, 'redirect' => "/view_gallery?gallery_id=$id"];
}, 'blog.gallery.moderate');
$router->add('POST', '/albums/{id}', function (Request $request) {
    $public = $request->input('is_public');
    Albums::update($request->param('id'), $request->input('title') !== null ? $request->string('title', 190) : null, $public === null ? null : (bool) (int) $public);
    return [];
}, 'blog.gallery.moderate');
$router->add('DELETE', '/albums/{id}', function (Request $request) {
    Albums::delete($request->param('id'));
    return ['redirect' => '/gallery'];
}, 'blog.gallery.moderate');
$router->add('POST', '/albums/{id}/publish', function (Request $request) {
    $ids = (array) $request->input('photos', []);
    return ['changed' => Albums::publish($request->param('id'), $ids, (bool) $request->int('published'))];
}, 'blog.gallery.moderate');
$router->add('POST', '/albums/{id}/photos/delete', function (Request $request) {
    return ['removed' => Albums::deletePhotos($request->param('id'), (array) $request->input('photos', []), current_identity())];
}, 'blog.gallery.create');
$router->add('POST', '/albums/{id}/photos', function (Request $request) {
    $chunk = $request->file('chunk');
    if (!$chunk) {
        throw new ApiException('Bilden kom inte fram. Den kan vara för stor.', 413);
    }
    $photo = Albums::receiveChunk($request->param('id'), current_identity(), [
        'upload_id' => $request->string('upload_id', 64),
        'file_name' => $request->string('file_name', 255),
        'file_size' => $request->int('file_size'),
        'index' => $request->int('chunk_index', -1),
        'total' => $request->int('total_chunks'),
    ], $chunk);
    return $photo ? ['photo' => $photo] : ['partial' => true];
}, 'blog.gallery.create');

// Vinrating.

$router->add('POST', '/wines', function (Request $request) {
    if (!Wines::canManage(current_identity())) {
        throw new ApiException('Bara administratörer kan lägga till viner.', 403);
    }
    return ['id' => Wines::create($request->string('title', 190), $request->string('description', 2000), $request->file('image'))];
}, 'user');
$router->add('POST', '/wines/{id}', function (Request $request) {
    if (!Wines::canManage(current_identity())) {
        throw new ApiException('Bara administratörer kan ändra viner.', 403);
    }
    Wines::update($request->param('id'), $request->string('title', 190), $request->string('description', 2000));
    return [];
}, 'user');
$router->add('DELETE', '/wines/{id}', function (Request $request) {
    if (!Wines::canManage(current_identity())) {
        throw new ApiException('Bara administratörer kan ta bort viner.', 403);
    }
    Wines::delete($request->param('id'));
    return [];
}, 'user');
$router->add('POST', '/wines/{id}/rating', function (Request $request) {
    if (!Wines::canRate(current_identity())) {
        throw new ApiException('Du har inte behörighet att betygsätta!', 403);
    }
    Wines::rate($request->param('id'), current_identity(), (float) $request->input('rating', 0), $request->string('comment', 1000));
    return [];
}, 'user');

// Admin area.
$router->add('POST', '/posts/{id}/visibility', function (Request $request) {
    Blog\Domain\Admin::setPublic($request->param('id'), (bool) $request->int('public'));
    return [];
}, 'blog.post.moderate');

$router->add('POST', '/videos', function (Request $request) {
    $id = Blog\Domain\Videos::receiveChunk(current_identity(), [
        'upload_id' => $request->string('upload_id', 64),
        'file_size' => $request->int('file_size'),
        'index' => $request->int('chunk_index', -1),
        'total' => $request->int('total_chunks'),
    ], $request->file('chunk'), $request->string('content', 2000), (bool) $request->int('is_public'));
    return $id === null ? ['partial' => true] : ['id' => $id];
}, 'blog.post.moderate');
$router->add('POST', '/videos/{id}', function (Request $request) {
    $public = $request->input('is_public');
    Blog\Domain\Videos::update($request->param('id'), $request->input('content') !== null ? $request->string('content', 2000) : null, $public === null ? null : (bool) (int) $public);
    return [];
}, 'blog.post.moderate');
$router->add('DELETE', '/videos/{id}', function (Request $request) {
    Blog\Domain\Videos::delete($request->param('id'));
    return [];
}, 'blog.post.moderate');

$router->add('POST', '/notices', function (Request $request) {
    return ['sent' => Blog\Domain\Admin::sendNotice((array) $request->input('users', []), $request->string('message', 1000))];
}, 'blog.notice.moderate');
$router->add('DELETE', '/notices/{id}', function (Request $request) {
    Blog\Domain\Admin::deleteNotice($request->param('id'));
    return [];
}, 'blog.notice.moderate');

// Widgets on the PlainShow dashboard, asked for by core with a signed request.
$router->add('GET', '/widgets/latest-posts', fn () => Blog\Domain\DashboardWidgets::handle('latest-posts'), null);
$router->add('GET', '/widgets/new-photos', fn () => Blog\Domain\DashboardWidgets::handle('new-photos'), null);
