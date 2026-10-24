<?php

if (!function_exists('postsEnsureSessionStarted')) {
    function postsEnsureSessionStarted()
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once dirname(__DIR__) . '/auth/plainshow.php';
            wocCurrentUser();
        }
    }
}

postsEnsureSessionStarted();

if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/connect.php';
}

if (!defined('POSTS_STORAGE_DIR_CANDIDATES')) {
    define('POSTS_STORAGE_DIR_CANDIDATES', [
        '/var/www/protected_pics/',
        '/var/www/html/projects/hugohansson/woc/app/storage/posts/',
    ]);
}

if (!defined('POSTS_WEBP_QUALITY')) {
    define('POSTS_WEBP_QUALITY', 82);
}

function postsSendJson($statusCode, $payload)
{
    http_response_code((int)$statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit();
}

function postsWantsJsonResponse()
{
    $acceptHeader = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string)$_SERVER['HTTP_ACCEPT']) : '';
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    return $isAjax
        || strpos($acceptHeader, 'application/json') !== false
        || isset($_REQUEST['action']);
}

function postsGetCurrentUser($conn)
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT user_id, firstname, lastname, access_level FROM user WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

function postsAuthorizeUser($conn, $jsonMode = true)
{
    $user = postsGetCurrentUser($conn);
    if ($user) {
        return $user;
    }

    if ($jsonMode) {
        postsSendJson(401, ['ok' => false, 'message' => 'Inloggning krävs.']);
    }

    http_response_code(401);
    echo 'Inloggning krävs.';
    return null;
}

function postsGetPostOwnerId($conn, $postId)
{
    $postId = (int)$postId;
    if ($postId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT user FROM blog_data WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    return (int)$row['user'];
}

function postsCanEditPost($conn, $postId, $user)
{
    $ownerId = postsGetPostOwnerId($conn, $postId);
    if ($ownerId === null) {
        return false;
    }

    if ((int)$user['access_level'] >= 4) {
        return true;
    }

    return (int)$user['user_id'] === $ownerId;
}

function postsNormalizeTitle($title)
{
    $title = trim((string)$title);
    if ($title === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($title, 0, 255);
    }

    return substr($title, 0, 255);
}

function postsNormalizePublicFlag($value)
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
}

function postsResolveStorageDirectory()
{
    static $resolvedDirectory = null;

    if ($resolvedDirectory !== null) {
        return $resolvedDirectory;
    }

    $candidates = POSTS_STORAGE_DIR_CANDIDATES;
    if (!is_array($candidates) || count($candidates) === 0) {
        $candidates = ['/var/www/protected_pics/'];
    }

    foreach ($candidates as $candidate) {
        $candidate = rtrim((string)$candidate, '/\\');
        if ($candidate === '') {
            continue;
        }

        if (!is_dir($candidate)) {
            if (!@mkdir($candidate, 0775, true) && !is_dir($candidate)) {
                continue;
            }
        }

        if (is_writable($candidate)) {
            $resolvedDirectory = $candidate . DIRECTORY_SEPARATOR;
            return $resolvedDirectory;
        }
    }

    $resolvedDirectory = '';
    return $resolvedDirectory;
}

function postsEnsureStorageDirectory()
{
    return postsResolveStorageDirectory() !== '';
}

function postsStorageErrorMessage()
{
    $candidates = POSTS_STORAGE_DIR_CANDIDATES;
    if (!is_array($candidates) || count($candidates) === 0) {
        return 'Bildmappen är inte skrivbar.';
    }

    $listed = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '') {
            $listed[] = $candidate;
        }
    }

    if (count($listed) === 0) {
        return 'Bildmappen är inte skrivbar.';
    }

    return 'Bildmappen är inte skrivbar. Kontrollerade: ' . implode(', ', $listed);
}

function postsUploadErrorMessage($errorCode)
{
    switch ((int)$errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Den uppladdade bilden är större än serverns gräns.';
        case UPLOAD_ERR_PARTIAL:
            return 'Bilden laddades bara upp delvis.';
        case UPLOAD_ERR_NO_FILE:
            return 'Ingen bild laddades upp.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Temporär uppladdningsmapp saknas.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Kunde inte spara bilden till disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'Uppladdningen blockerades av en servertilläggsmodul.';
        default:
            return 'Okänt uppladdningsfel.';
    }
}

function postsCreateSourceImage($tmpPath, $mimeType)
{
    switch ($mimeType) {
        case 'image/jpeg':
            return @imagecreatefromjpeg($tmpPath);
        case 'image/png':
            return @imagecreatefrompng($tmpPath);
        case 'image/gif':
            return @imagecreatefromgif($tmpPath);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false;
        default:
            return false;
    }
}

function postsCorrectOrientation($image, $tmpPath, $mimeType)
{
    if ($mimeType !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($tmpPath);
    if (!$exif || empty($exif['Orientation'])) {
        return $image;
    }

    switch ((int)$exif['Orientation']) {
        case 3:
            $image = imagerotate($image, 180, 0);
            break;
        case 6:
            $image = imagerotate($image, -90, 0);
            break;
        case 8:
            $image = imagerotate($image, 90, 0);
            break;
    }

    return $image;
}

function postsCreateUniqueFilename()
{
    try {
        $token = bin2hex(random_bytes(12));
    } catch (Exception $exception) {
        $token = str_replace('.', '', uniqid('', true));
    }

    return 'post_' . $token . '.webp';
}

function postsStoreImageFromUploadArray($file)
{
    if (!is_array($file)) {
        return ['ok' => false, 'message' => 'Ogiltig bilddata.'];
    }

    $errorCode = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => postsUploadErrorMessage($errorCode)];
    }

    $tmpPath = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
    if ($tmpPath === '' || (!is_uploaded_file($tmpPath) && !is_file($tmpPath))) {
        return ['ok' => false, 'message' => 'Temporär uppladdningsfil saknas.'];
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo || empty($imageInfo['mime'])) {
        return ['ok' => false, 'message' => 'Den uppladdade filen är inte en giltig bild.'];
    }

    $mimeType = strtolower((string)$imageInfo['mime']);
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        return ['ok' => false, 'message' => 'Endast JPEG, PNG, GIF och WebP är tillåtna.'];
    }

    $sourceImage = postsCreateSourceImage($tmpPath, $mimeType);
    if (!$sourceImage) {
        return ['ok' => false, 'message' => 'Kunde inte läsa den uppladdade bilden.'];
    }

    $sourceImage = postsCorrectOrientation($sourceImage, $tmpPath, $mimeType);

    $storageDirectory = postsResolveStorageDirectory();
    if ($storageDirectory === '') {
        imagedestroy($sourceImage);
        return ['ok' => false, 'message' => postsStorageErrorMessage()];
    }

    $filename = postsCreateUniqueFilename();
    $destination = rtrim($storageDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;

    $saved = @imagewebp($sourceImage, $destination, POSTS_WEBP_QUALITY);
    imagedestroy($sourceImage);

    if (!$saved) {
        return ['ok' => false, 'message' => 'Kunde inte konvertera bilden till WebP.'];
    }

    return ['ok' => true, 'path' => $destination, 'filename' => $filename];
}

function postsDeleteFile($filePath)
{
    $filePath = trim((string)$filePath);
    if ($filePath === '') {
        return;
    }

    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

function postsImageIsReferenced($conn, $imagePath)
{
    $imagePath = trim((string)$imagePath);
    if ($imagePath === '') {
        return false;
    }

    $stmt = $conn->prepare("SELECT 1 FROM blog_data WHERE image = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $imagePath);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return true;
        }
    }

    $stmt = $conn->prepare("SELECT 1 FROM blog_images WHERE image_path = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $imagePath);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return true;
        }
    }

    return false;
}

function postsGetUploadsMap()
{
    $uploads = [];
    if (!isset($_FILES['uploads']) || !is_array($_FILES['uploads']) || !isset($_FILES['uploads']['name'])) {
        return $uploads;
    }

    if (!is_array($_FILES['uploads']['name'])) {
        return $uploads;
    }

    foreach ($_FILES['uploads']['name'] as $key => $name) {
        $uploads[(string)$key] = [
            'name' => $name,
            'type' => isset($_FILES['uploads']['type'][$key]) ? $_FILES['uploads']['type'][$key] : '',
            'tmp_name' => isset($_FILES['uploads']['tmp_name'][$key]) ? $_FILES['uploads']['tmp_name'][$key] : '',
            'error' => isset($_FILES['uploads']['error'][$key]) ? $_FILES['uploads']['error'][$key] : UPLOAD_ERR_NO_FILE,
            'size' => isset($_FILES['uploads']['size'][$key]) ? $_FILES['uploads']['size'][$key] : 0,
        ];
    }

    return $uploads;
}

function postsGetLegacySectionImageFiles($sectionIndex)
{
    $files = [];
    if (!isset($_FILES['sections']['name'][$sectionIndex]['images'])) {
        return $files;
    }

    if (!is_array($_FILES['sections']['name'][$sectionIndex]['images'])) {
        return $files;
    }

    $names = $_FILES['sections']['name'][$sectionIndex]['images'];
    foreach ($names as $imageIndex => $name) {
        $errorCode = isset($_FILES['sections']['error'][$sectionIndex]['images'][$imageIndex])
            ? (int)$_FILES['sections']['error'][$sectionIndex]['images'][$imageIndex]
            : UPLOAD_ERR_NO_FILE;

        if ($name === '' && $errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => $name,
            'type' => isset($_FILES['sections']['type'][$sectionIndex]['images'][$imageIndex])
                ? $_FILES['sections']['type'][$sectionIndex]['images'][$imageIndex]
                : '',
            'tmp_name' => isset($_FILES['sections']['tmp_name'][$sectionIndex]['images'][$imageIndex])
                ? $_FILES['sections']['tmp_name'][$sectionIndex]['images'][$imageIndex]
                : '',
            'error' => $errorCode,
            'size' => isset($_FILES['sections']['size'][$sectionIndex]['images'][$imageIndex])
                ? $_FILES['sections']['size'][$sectionIndex]['images'][$imageIndex]
                : 0,
        ];
    }

    return $files;
}

function postsParseBlocksFromRequest()
{
    $result = ['ok' => true, 'structured' => false, 'blocks' => [], 'message' => ''];

    $sectionsJson = isset($_POST['sections_json']) ? trim((string)$_POST['sections_json']) : '';
    if ($sectionsJson !== '') {
        $result['structured'] = true;
        $decoded = json_decode($sectionsJson, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'structured' => true, 'blocks' => [], 'message' => 'Ogiltig blockdata.'];
        }

        $uploads = postsGetUploadsMap();
        foreach ($decoded as $block) {
            if (!is_array($block) || !isset($block['type'])) {
                continue;
            }

            $type = strtolower(trim((string)$block['type']));
            if ($type === 'text') {
                $content = isset($block['content']) ? trim((string)$block['content']) : '';
                if ($content !== '') {
                    $result['blocks'][] = ['type' => 'text', 'content' => $content];
                }
                continue;
            }

            if ($type !== 'image') {
                continue;
            }

            $source = isset($block['source']) ? strtolower(trim((string)$block['source'])) : 'upload';
            if ($source === 'existing') {
                $imagePath = isset($block['image_path']) ? trim((string)$block['image_path']) : '';
                if ($imagePath !== '') {
                    $result['blocks'][] = ['type' => 'image', 'source' => 'existing', 'image_path' => $imagePath];
                }
                continue;
            }

            $uploadKey = isset($block['upload_key']) ? trim((string)$block['upload_key']) : '';
            if ($uploadKey === '' || !isset($uploads[$uploadKey])) {
                continue;
            }

            $file = $uploads[$uploadKey];
            if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result['blocks'][] = ['type' => 'image', 'source' => 'upload', 'file' => $file];
        }

        return $result;
    }

    if (isset($_POST['sections']) && is_array($_POST['sections'])) {
        $result['structured'] = true;
        foreach ($_POST['sections'] as $sectionIndex => $section) {
            $sectionType = isset($section['type']) ? strtolower(trim((string)$section['type'])) : '';
            $text = isset($section['content']) ? trim((string)$section['content']) : '';

            if ($sectionType === '' && $text !== '') {
                $sectionType = 'text';
            }

            if ($sectionType === 'text' && $text !== '') {
                $result['blocks'][] = ['type' => 'text', 'content' => $text];
            }

            $legacyImages = postsGetLegacySectionImageFiles($sectionIndex);
            foreach ($legacyImages as $legacyImage) {
                if ((int)$legacyImage['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $result['blocks'][] = ['type' => 'image', 'source' => 'upload', 'file' => $legacyImage];
            }
        }

        return $result;
    }

    $legacyContent = isset($_POST['content']) ? trim((string)$_POST['content']) : '';
    if ($legacyContent !== '') {
        $result['blocks'][] = ['type' => 'text', 'content' => $legacyContent];
    }

    if (isset($_FILES['image']) && is_array($_FILES['image'])) {
        $errorCode = isset($_FILES['image']['error']) ? (int)$_FILES['image']['error'] : UPLOAD_ERR_NO_FILE;
        if ($errorCode !== UPLOAD_ERR_NO_FILE) {
            $result['blocks'][] = ['type' => 'image', 'source' => 'upload', 'file' => $_FILES['image']];
        }
    }

    return $result;
}

function postsMaterializeBlocks($conn, $rawBlocks, $allowedExistingPaths = [])
{
    $resolved = [];
    $createdPaths = [];
    $errors = [];
    $allowExisting = !empty($allowedExistingPaths);

    $allowedLookup = [];
    foreach ($allowedExistingPaths as $path) {
        $path = trim((string)$path);
        if ($path !== '') {
            $allowedLookup[$path] = true;
        }
    }

    foreach ($rawBlocks as $rawBlock) {
        if (!is_array($rawBlock) || !isset($rawBlock['type'])) {
            continue;
        }

        $type = strtolower(trim((string)$rawBlock['type']));
        if ($type === 'text') {
            $content = isset($rawBlock['content']) ? trim((string)$rawBlock['content']) : '';
            if ($content !== '') {
                $resolved[] = ['type' => 'text', 'content' => $content];
            }
            continue;
        }

        if ($type !== 'image') {
            continue;
        }

        $source = isset($rawBlock['source']) ? strtolower(trim((string)$rawBlock['source'])) : 'upload';
        if ($source === 'existing') {
            $imagePath = isset($rawBlock['image_path']) ? trim((string)$rawBlock['image_path']) : '';
            if ($imagePath === '') {
                continue;
            }

            if (!$allowExisting || !isset($allowedLookup[$imagePath])) {
                $errors[] = 'Ogiltig referens till befintlig bild.';
                continue;
            }

            $resolved[] = ['type' => 'image', 'image_path' => $imagePath];
            continue;
        }

        if (!isset($rawBlock['file']) || !is_array($rawBlock['file'])) {
            continue;
        }

        $storeResult = postsStoreImageFromUploadArray($rawBlock['file']);
        if (!$storeResult['ok']) {
            $errors[] = $storeResult['message'];
            continue;
        }

        $resolved[] = ['type' => 'image', 'image_path' => $storeResult['path']];
        $createdPaths[] = $storeResult['path'];
    }

    return [
        'ok' => empty($errors),
        'blocks' => $resolved,
        'created_paths' => $createdPaths,
        'errors' => $errors,
    ];
}

function postsBuildSummaryFromBlocks($blocks)
{
    $summaryContent = '';
    $summaryImage = '';

    foreach ($blocks as $block) {
        if ($summaryContent === '' && isset($block['type']) && $block['type'] === 'text') {
            $content = trim((string)$block['content']);
            if ($content !== '') {
                $summaryContent = $content;
            }
        }

        if ($summaryImage === '' && isset($block['type']) && $block['type'] === 'image') {
            $imagePath = trim((string)$block['image_path']);
            if ($imagePath !== '') {
                $summaryImage = $imagePath;
            }
        }
    }

    if ($summaryContent === '') {
        $summaryContent = '...';
    }

    return ['content' => $summaryContent, 'image' => $summaryImage];
}

function postsInsertStructuredBlocks($conn, $postId, $blocks)
{
    $postId = (int)$postId;
    $sectionStmt = $conn->prepare("INSERT INTO blog_sections (post_id, section_type, content) VALUES (?, ?, ?)");
    if (!$sectionStmt) {
        throw new RuntimeException('Kunde inte förbereda skapande av block.');
    }

    $imageStmt = $conn->prepare("INSERT INTO blog_images (post_id, section_id, image_path) VALUES (?, ?, ?)");
    if (!$imageStmt) {
        throw new RuntimeException('Kunde inte förbereda skapande av bildblock.');
    }

    foreach ($blocks as $block) {
        $type = (isset($block['type']) && $block['type'] === 'image') ? 'image' : 'text';
        $content = ($type === 'text') ? (string)$block['content'] : '';

        $sectionStmt->bind_param('iss', $postId, $type, $content);
        if (!$sectionStmt->execute()) {
            throw new RuntimeException('Kunde inte skapa block.');
        }

        $sectionId = (int)$conn->insert_id;
        if ($type === 'image') {
            $imagePath = (string)$block['image_path'];
            $imageStmt->bind_param('iis', $postId, $sectionId, $imagePath);
            if (!$imageStmt->execute()) {
                throw new RuntimeException('Kunde inte skapa bild i block.');
            }
        }
    }
}

function postsReplaceStructuredBlocks($conn, $postId, $blocks)
{
    $postId = (int)$postId;

    $deleteImagesStmt = $conn->prepare("DELETE FROM blog_images WHERE post_id = ?");
    if (!$deleteImagesStmt) {
        throw new RuntimeException('Kunde inte förbereda rensning av bilder.');
    }
    $deleteImagesStmt->bind_param('i', $postId);
    if (!$deleteImagesStmt->execute()) {
        throw new RuntimeException('Kunde inte rensa befintliga postbilder.');
    }

    $deleteSectionsStmt = $conn->prepare("DELETE FROM blog_sections WHERE post_id = ?");
    if (!$deleteSectionsStmt) {
        throw new RuntimeException('Kunde inte förbereda rensning av block.');
    }
    $deleteSectionsStmt->bind_param('i', $postId);
    if (!$deleteSectionsStmt->execute()) {
        throw new RuntimeException('Kunde inte rensa befintliga block.');
    }

    postsInsertStructuredBlocks($conn, $postId, $blocks);
}

function postsGetPostImagePaths($conn, $postId)
{
    $postId = (int)$postId;
    $paths = [];

    $stmt = $conn->prepare("SELECT image FROM blog_data WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && ($row = $result->fetch_assoc())) {
            $path = trim((string)$row['image']);
            if ($path !== '') {
                $paths[] = $path;
            }
        }
    }

    $stmt = $conn->prepare("SELECT image_path FROM blog_images WHERE post_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $path = trim((string)$row['image_path']);
            if ($path !== '') {
                $paths[] = $path;
            }
        }
    }

    return array_values(array_unique($paths));
}

function postsCleanupUnreferencedImages($conn, $imagePaths)
{
    $imagePaths = array_values(array_unique($imagePaths));
    foreach ($imagePaths as $imagePath) {
        $imagePath = trim((string)$imagePath);
        if ($imagePath === '') {
            continue;
        }

        if (!postsImageIsReferenced($conn, $imagePath)) {
            postsDeleteFile($imagePath);
        }
    }
}

function postsCreatePost($conn, $user)
{
    $title = postsNormalizeTitle(isset($_POST['title']) ? $_POST['title'] : '');
    if ($title === '') {
        return ['ok' => false, 'message' => 'Titel krävs.'];
    }

    $isPublic = postsNormalizePublicFlag(isset($_POST['is_public']) ? $_POST['is_public'] : 0);
    $parsedBlocks = postsParseBlocksFromRequest();
    if (!$parsedBlocks['ok']) {
        return ['ok' => false, 'message' => $parsedBlocks['message']];
    }

    $materialized = postsMaterializeBlocks($conn, $parsedBlocks['blocks'], []);
    if (!$materialized['ok']) {
        postsCleanupUnreferencedImages($conn, $materialized['created_paths']);
        return ['ok' => false, 'message' => implode(' ', $materialized['errors'])];
    }

    $blocks = $materialized['blocks'];
    if (count($blocks) === 0) {
        postsCleanupUnreferencedImages($conn, $materialized['created_paths']);
        return ['ok' => false, 'message' => 'Lägg till minst ett textblock eller bildblock innan publicering.'];
    }

    $summary = postsBuildSummaryFromBlocks($blocks);
    $postId = 0;

    $conn->begin_transaction();
    try {
        $userId = (int)$user['user_id'];
        $date = date('Y-m-d');
        $summaryContent = $summary['content'];
        $summaryImage = $summary['image'];

        $insertPostStmt = $conn->prepare(
            "INSERT INTO blog_data (user, date, title, content, image, is_public) VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$insertPostStmt) {
            throw new RuntimeException('Kunde inte förbereda skapande av inlägg.');
        }

        $insertPostStmt->bind_param('issssi', $userId, $date, $title, $summaryContent, $summaryImage, $isPublic);
        if (!$insertPostStmt->execute()) {
            throw new RuntimeException('Kunde inte skapa inlägg.');
        }

        $postId = (int)$conn->insert_id;
        postsInsertStructuredBlocks($conn, $postId, $blocks);

        $conn->commit();
    } catch (Throwable $throwable) {
        $conn->rollback();
        postsCleanupUnreferencedImages($conn, $materialized['created_paths']);
        return ['ok' => false, 'message' => $throwable->getMessage()];
    }

    return ['ok' => true, 'message' => 'Inlägget skapades.', 'post_id' => $postId];
}

function postsUpdateLegacyPostSummary($conn, $postId, $title, $isPublic)
{
    $postId = (int)$postId;
    $currentStmt = $conn->prepare("SELECT content, image FROM blog_data WHERE id = ? LIMIT 1");
    if (!$currentStmt) {
        return ['ok' => false, 'message' => 'Kunde inte läsa befintliga inläggsdata.'];
    }

    $currentStmt->bind_param('i', $postId);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    if (!$currentResult || $currentResult->num_rows === 0) {
        return ['ok' => false, 'message' => 'Inlägget hittades inte.'];
    }

    $current = $currentResult->fetch_assoc();
    $existingContent = (string)$current['content'];
    $existingImage = (string)$current['image'];

    $content = array_key_exists('content', $_POST) ? trim((string)$_POST['content']) : $existingContent;
    if ($content === '') {
        $content = $existingContent !== '' ? $existingContent : '...';
    }

    $newImagePath = $existingImage;
    $newCreatedPaths = [];

    if (isset($_FILES['image']) && is_array($_FILES['image'])) {
        $uploadError = isset($_FILES['image']['error']) ? (int)$_FILES['image']['error'] : UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_NO_FILE) {
            $storedImage = postsStoreImageFromUploadArray($_FILES['image']);
            if (!$storedImage['ok']) {
                return ['ok' => false, 'message' => $storedImage['message']];
            }
            $newImagePath = $storedImage['path'];
            $newCreatedPaths[] = $storedImage['path'];
        }
    }

    $updateStmt = $conn->prepare("UPDATE blog_data SET title = ?, content = ?, image = ?, is_public = ? WHERE id = ?");
    if (!$updateStmt) {
        postsCleanupUnreferencedImages($conn, $newCreatedPaths);
        return ['ok' => false, 'message' => 'Kunde inte förbereda uppdatering av inlägg.'];
    }

    $updateStmt->bind_param('sssii', $title, $content, $newImagePath, $isPublic, $postId);
    if (!$updateStmt->execute()) {
        postsCleanupUnreferencedImages($conn, $newCreatedPaths);
        return ['ok' => false, 'message' => 'Kunde inte uppdatera inlägget.'];
    }

    if ($newImagePath !== $existingImage && $existingImage !== '') {
        postsCleanupUnreferencedImages($conn, [$existingImage]);
    }

    return ['ok' => true, 'message' => 'Inlägget uppdaterades.', 'post_id' => $postId];
}

function postsUpdateStructuredPost($conn, $postId, $title, $isPublic, $parsedBlocks)
{
    $postId = (int)$postId;
    $existingPaths = postsGetPostImagePaths($conn, $postId);

    $materialized = postsMaterializeBlocks($conn, $parsedBlocks['blocks'], $existingPaths);
    if (!$materialized['ok']) {
        postsCleanupUnreferencedImages($conn, $materialized['created_paths']);
        return ['ok' => false, 'message' => implode(' ', $materialized['errors'])];
    }

    $blocks = $materialized['blocks'];
    if (count($blocks) === 0) {
        postsCleanupUnreferencedImages($conn, $materialized['created_paths']);
        return ['ok' => false, 'message' => 'Ett inlägg måste innehålla minst ett textblock eller bildblock.'];
    }

    $summary = postsBuildSummaryFromBlocks($blocks);
    $summaryContent = $summary['content'];
    $summaryImage = $summary['image'];

    $conn->begin_transaction();
    try {
        postsReplaceStructuredBlocks($conn, $postId, $blocks);

        $updateStmt = $conn->prepare("UPDATE blog_data SET title = ?, content = ?, image = ?, is_public = ? WHERE id = ?");
        if (!$updateStmt) {
            throw new RuntimeException('Kunde inte förbereda uppdatering av inlägg.');
        }

        $updateStmt->bind_param('sssii', $title, $summaryContent, $summaryImage, $isPublic, $postId);
        if (!$updateStmt->execute()) {
            throw new RuntimeException('Kunde inte uppdatera inläggets sammanfattning.');
        }

        $conn->commit();
    } catch (Throwable $throwable) {
        $conn->rollback();
        postsCleanupUnreferencedImages($conn, $materialized['created_paths']);
        return ['ok' => false, 'message' => $throwable->getMessage()];
    }

    $keptImages = [];
    foreach ($blocks as $block) {
        if (isset($block['type']) && $block['type'] === 'image' && !empty($block['image_path'])) {
            $keptImages[] = $block['image_path'];
        }
    }

    $toCleanup = array_diff($existingPaths, $keptImages);
    postsCleanupUnreferencedImages($conn, $toCleanup);

    return ['ok' => true, 'message' => 'Inlägget uppdaterades.', 'post_id' => $postId];
}

function postsUpdatePost($conn, $user, $postId)
{
    $postId = (int)$postId;
    if ($postId <= 0) {
        return ['ok' => false, 'message' => 'Ogiltigt inläggs-id.'];
    }

    if (!postsCanEditPost($conn, $postId, $user)) {
        return ['ok' => false, 'message' => 'Du har inte behörighet att redigera detta inlägg.'];
    }

    $title = postsNormalizeTitle(isset($_POST['title']) ? $_POST['title'] : '');
    if ($title === '') {
        return ['ok' => false, 'message' => 'Titel krävs.'];
    }

    $isPublic = postsNormalizePublicFlag(isset($_POST['is_public']) ? $_POST['is_public'] : 0);
    $parsedBlocks = postsParseBlocksFromRequest();
    if (!$parsedBlocks['ok']) {
        return ['ok' => false, 'message' => $parsedBlocks['message']];
    }

    if ($parsedBlocks['structured']) {
        return postsUpdateStructuredPost($conn, $postId, $title, $isPublic, $parsedBlocks);
    }

    return postsUpdateLegacyPostSummary($conn, $postId, $title, $isPublic);
}

function postsDeletePost($conn, $postId)
{
    $postId = (int)$postId;
    if ($postId <= 0) {
        return ['ok' => false, 'message' => 'Ogiltigt inläggs-id.'];
    }

    $imagePaths = postsGetPostImagePaths($conn, $postId);
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("DELETE FROM likes WHERE blog_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $postId);
            $stmt->execute();
        }

        $stmt = $conn->prepare("DELETE FROM blog_comments WHERE blog_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $postId);
            $stmt->execute();
        }

        $stmt = $conn->prepare("DELETE FROM blog_images WHERE post_id = ?");
        if (!$stmt) {
            throw new RuntimeException('Kunde inte förbereda borttagning av bilder.');
        }
        $stmt->bind_param('i', $postId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Kunde inte ta bort inläggets bilder.');
        }

        $stmt = $conn->prepare("DELETE FROM blog_sections WHERE post_id = ?");
        if (!$stmt) {
            throw new RuntimeException('Kunde inte förbereda borttagning av block.');
        }
        $stmt->bind_param('i', $postId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Kunde inte ta bort inläggets block.');
        }

        $stmt = $conn->prepare("DELETE FROM blog_data WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Kunde inte förbereda borttagning av inlägg.');
        }
        $stmt->bind_param('i', $postId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Kunde inte ta bort inlägget.');
        }

        $conn->commit();
    } catch (Throwable $throwable) {
        $conn->rollback();
        return ['ok' => false, 'message' => $throwable->getMessage()];
    }

    postsCleanupUnreferencedImages($conn, $imagePaths);
    return ['ok' => true, 'message' => 'Inlägget togs bort.'];
}

function postsFetchPostForEditor($conn, $postId)
{
    $postId = (int)$postId;
    $stmt = $conn->prepare("SELECT id, user, title, content, image, is_public, date FROM blog_data WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        return null;
    }

    $post = $result->fetch_assoc();
    $sections = [];

    $stmt = $conn->prepare(
        "SELECT bs.id AS section_id, bs.section_type, bs.content, bi.id AS image_id, bi.image_path
         FROM blog_sections bs
         LEFT JOIN blog_images bi ON bi.section_id = bs.id
         WHERE bs.post_id = ?
         ORDER BY bs.id ASC, bi.id ASC"
    );

    $hasStructuredRows = false;
    if ($stmt) {
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($rows && ($row = $rows->fetch_assoc())) {
            $hasStructuredRows = true;
            if ($row['section_type'] === 'text') {
                $text = trim((string)$row['content']);
                if ($text !== '') {
                    $sections[] = ['type' => 'text', 'content' => $text];
                }
                continue;
            }

            if ($row['section_type'] === 'image') {
                $imagePath = trim((string)$row['image_path']);
                if ($imagePath !== '') {
                    $sections[] = ['type' => 'image', 'source' => 'existing', 'image_path' => $imagePath];
                }
            }
        }
    }

    if (!$hasStructuredRows) {
        $fallbackText = trim((string)$post['content']);
        $fallbackImage = trim((string)$post['image']);

        if ($fallbackText !== '' && $fallbackText !== '...') {
            $sections[] = ['type' => 'text', 'content' => $fallbackText];
        }
        if ($fallbackImage !== '') {
            $sections[] = ['type' => 'image', 'source' => 'existing', 'image_path' => $fallbackImage];
        }
    }

    return [
        'id' => (int)$post['id'],
        'user' => (int)$post['user'],
        'title' => (string)$post['title'],
        'is_public' => (int)$post['is_public'],
        'date' => (string)$post['date'],
        'sections' => $sections,
    ];
}

function postsHandleProxyRequest($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        postsSendJson(405, ['ok' => false, 'message' => 'Metoden är inte tillåten.']);
    }

    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
    if ($action === '' && isset($_GET['action'])) {
        $action = trim((string)$_GET['action']);
    }

    if ($action === '') {
        postsSendJson(400, ['ok' => false, 'message' => 'Saknad action.']);
    }

    $user = postsAuthorizeUser($conn, true);

    if ($action === 'create_post') {
        $result = postsCreatePost($conn, $user);
        postsSendJson($result['ok'] ? 201 : 400, $result);
    }

    if ($action === 'get_post') {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if ($postId <= 0) {
            postsSendJson(400, ['ok' => false, 'message' => 'Ogiltigt inläggs-id.']);
        }

        if (!postsCanEditPost($conn, $postId, $user)) {
            postsSendJson(403, ['ok' => false, 'message' => 'Åtkomst nekad.']);
        }

        $post = postsFetchPostForEditor($conn, $postId);
        if (!$post) {
            postsSendJson(404, ['ok' => false, 'message' => 'Inlägget hittades inte.']);
        }

        postsSendJson(200, ['ok' => true, 'post' => $post]);
    }

    if ($action === 'update_post') {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
        $result = postsUpdatePost($conn, $user, $postId);
        postsSendJson($result['ok'] ? 200 : 400, $result);
    }

    if ($action === 'delete_post') {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
        if ($postId <= 0) {
            postsSendJson(400, ['ok' => false, 'message' => 'Ogiltigt inläggs-id.']);
        }

        if (!postsCanEditPost($conn, $postId, $user)) {
            postsSendJson(403, ['ok' => false, 'message' => 'Åtkomst nekad.']);
        }

        $result = postsDeletePost($conn, $postId);
        postsSendJson($result['ok'] ? 200 : 400, $result);
    }

    postsSendJson(400, ['ok' => false, 'message' => 'Okänd action.']);
}

function postsHandleLegacyLogicRequest($conn)
{
    $isCreate = isset($_REQUEST['new_post']);
    $isUpdate = isset($_POST['update_post']);
    $isDelete = isset($_REQUEST['delete_blog_post']);

    if (!$isCreate && !$isUpdate && !$isDelete) {
        return false;
    }

    $jsonMode = postsWantsJsonResponse();
    $user = postsAuthorizeUser($conn, $jsonMode);
    if (!$user) {
        return true;
    }

    if ($isCreate) {
        $result = postsCreatePost($conn, $user);
    } elseif ($isUpdate) {
        $postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $result = postsUpdatePost($conn, $user, $postId);
    } else {
        $postId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if (!postsCanEditPost($conn, $postId, $user)) {
            $result = ['ok' => false, 'message' => 'Åtkomst nekad.'];
        } else {
            $result = postsDeletePost($conn, $postId);
        }
    }

    if ($jsonMode) {
        postsSendJson($result['ok'] ? 200 : 400, $result);
    }

    if ($result['ok']) {
        $query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
            ? '?' . $_SERVER['QUERY_STRING']
            : '';
        header('Location:' . $_SERVER['PHP_SELF'] . $query);
        exit();
    }

    http_response_code(400);
    echo htmlspecialchars((string)$result['message']);
    return true;
}
