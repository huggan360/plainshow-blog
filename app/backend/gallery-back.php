<?php

if (!function_exists('galleryEnsureSessionStarted')) {
    function galleryEnsureSessionStarted()
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once dirname(__DIR__) . '/auth/plainshow.php';
            wocCurrentUser();
        }
    }
}

galleryEnsureSessionStarted();

if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/connect.php';
}

if (!defined('GALLERY_STORAGE_DIR')) {
    define('GALLERY_STORAGE_DIR', '/var/www/photo_album/');
}
if (!defined('GALLERY_WEBP_QUALITY')) {
    define('GALLERY_WEBP_QUALITY', 82);
}
if (!defined('GALLERY_MAX_UPLOAD_BYTES')) {
    define('GALLERY_MAX_UPLOAD_BYTES', 50 * 1024 * 1024);
}

function galleryIniToBytes($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }

    $lastChar = strtolower(substr($value, -1));
    $number = (float)$value;
    switch ($lastChar) {
        case 'g':
            $number *= 1024;
        case 'm':
            $number *= 1024;
        case 'k':
            $number *= 1024;
            break;
    }

    return (int)$number;
}

function galleryFormatBytes($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = (int)floor(log($bytes, 1024));
    $index = max(0, min($index, count($units) - 1));
    $size = $bytes / pow(1024, $index);

    return sprintf($size >= 100 || $index === 0 ? '%.0f %s' : '%.1f %s', $size, $units[$index]);
}

function galleryGetServerUploadLimitBytes()
{
    $postMax = galleryIniToBytes(ini_get('post_max_size'));
    $uploadMax = galleryIniToBytes(ini_get('upload_max_filesize'));

    if ($postMax > 0 && $uploadMax > 0) {
        return min($postMax, $uploadMax);
    }
    if ($postMax > 0) {
        return $postMax;
    }

    return $uploadMax;
}

function galleryGetChunkDirectory()
{
    return rtrim(GALLERY_STORAGE_DIR, '/\\') . DIRECTORY_SEPARATOR . '.chunks' . DIRECTORY_SEPARATOR;
}

function galleryEnsureChunkDirectory()
{
    $dir = galleryGetChunkDirectory();
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }
    return is_writable($dir);
}

function gallerySanitizeUploadId($uploadId)
{
    $uploadId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$uploadId);
    if ($uploadId === null) {
        return '';
    }
    return substr($uploadId, 0, 64);
}

function galleryCleanupChunkUpload($uploadId)
{
    $uploadId = gallerySanitizeUploadId($uploadId);
    if ($uploadId === '') {
        return;
    }

    $dir = galleryGetChunkDirectory();
    @unlink($dir . $uploadId . '.part');
    @unlink($dir . $uploadId . '.json');
}

function galleryExceededPostLimit()
{
    $postMax = galleryIniToBytes(ini_get('post_max_size'));
    if ($postMax <= 0) {
        return false;
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    return $contentLength > $postMax;
}

function galleryBuildUploadLimitErrorMessage()
{
    $postMax = galleryIniToBytes(ini_get('post_max_size'));
    $uploadMax = galleryIniToBytes(ini_get('upload_max_filesize'));
    $effective = galleryGetServerUploadLimitBytes();

    if ($effective <= 0) {
        return 'Upload request is too large for current server limits.';
    }

    return 'Upload too large for server limit (' . galleryFormatBytes($effective) . '). '
        . 'Current server limits: upload_max_filesize=' . galleryFormatBytes($uploadMax)
        . ', post_max_size=' . galleryFormatBytes($postMax) . '.';
}

function gallerySendJson($statusCode, $payload)
{
    http_response_code((int)$statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit();
}

function galleryGetCurrentUser($conn)
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

function galleryAuthorizeEditor($conn, $jsonMode = true)
{
    $user = galleryGetCurrentUser($conn);
    if (!$user) {
        if ($jsonMode) {
            gallerySendJson(401, ['ok' => false, 'message' => 'Authentication required.']);
        }
        http_response_code(401);
        echo "Authentication required.";
        return null;
    }

    if ((int)$user['access_level'] < 4) {
        if ($jsonMode) {
            gallerySendJson(403, ['ok' => false, 'message' => 'Access denied.']);
        }
        http_response_code(403);
        echo "Access denied.";
        return null;
    }

    return $user;
}

function galleryBindParams($stmt, $types, $values)
{
    $params = [];
    $params[] = &$types;
    foreach ($values as $index => $value) {
        $params[] = &$values[$index];
    }

    return call_user_func_array([$stmt, 'bind_param'], $params);
}

function galleryFirstArrayKey($array)
{
    foreach ($array as $key => $value) {
        return $key;
    }
    return null;
}

function galleryEnsureStorageDirectory()
{
    if (!is_dir(GALLERY_STORAGE_DIR)) {
        if (!@mkdir(GALLERY_STORAGE_DIR, 0755, true) && !is_dir(GALLERY_STORAGE_DIR)) {
            return false;
        }
    }

    return is_writable(GALLERY_STORAGE_DIR);
}

function galleryNormalizeFiles($fileField)
{
    $normalized = [];
    if (!isset($fileField['name'])) {
        return $normalized;
    }

    if (!is_array($fileField['name'])) {
        $normalized[] = $fileField;
        return $normalized;
    }

    $count = count($fileField['name']);
    for ($i = 0; $i < $count; $i++) {
        if (!isset($fileField['name'][$i]) || $fileField['name'][$i] === '') {
            continue;
        }
        $normalized[] = [
            'name' => $fileField['name'][$i],
            'type' => isset($fileField['type'][$i]) ? $fileField['type'][$i] : '',
            'tmp_name' => isset($fileField['tmp_name'][$i]) ? $fileField['tmp_name'][$i] : '',
            'error' => isset($fileField['error'][$i]) ? $fileField['error'][$i] : UPLOAD_ERR_NO_FILE,
            'size' => isset($fileField['size'][$i]) ? $fileField['size'][$i] : 0,
        ];
    }

    return $normalized;
}

function galleryUploadErrorMessage($errorCode)
{
    switch ((int)$errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File exceeds upload limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Temporary upload folder missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server failed to write file.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload blocked by server extension.';
        default:
            return 'Unknown upload error.';
    }
}

function galleryCreateSourceImage($tmpPath, $mimeType)
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

function galleryCorrectOrientation($image, $tmpPath, $mimeType)
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

function galleryGalleryExists($conn, $galleryId)
{
    $galleryId = (int)$galleryId;
    if ($galleryId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT 1 FROM galleries WHERE gallery_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $galleryId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result && $result->num_rows > 0;
}

function galleryCreateGallery($conn, $title, $isPublic = 0)
{
    $title = trim((string)$title);
    if ($title === '') {
        return ['ok' => false, 'message' => 'Gallery title is required.'];
    }
    if (strlen($title) > 190) {
        return ['ok' => false, 'message' => 'Gallery title is too long.'];
    }

    $publicValue = (int)$isPublic === 1 ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO galleries (title, is_public) VALUES (?, ?)");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Failed to prepare gallery insert query.'];
    }

    $stmt->bind_param('si', $title, $publicValue);
    if (!$stmt->execute()) {
        return ['ok' => false, 'message' => 'Failed to create gallery.'];
    }

    return ['ok' => true, 'gallery_id' => (int)$conn->insert_id];
}

function galleryUploadImage($conn, $galleryId, $userId, $file)
{
    return galleryUploadImageInternal($conn, $galleryId, $userId, $file, false);
}

function galleryUploadImageInternal($conn, $galleryId, $userId, $file, $allowLocalFile)
{
    $galleryId = (int)$galleryId;
    $userId = (int)$userId;

    if ($galleryId <= 0 || $userId <= 0) {
        return ['ok' => false, 'message' => 'Invalid gallery or user id.'];
    }

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'message' => 'Invalid uploaded file payload.'];
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => galleryUploadErrorMessage($file['error'])];
    }

    if (empty($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Temporary upload file missing.'];
    }
    if (!$allowLocalFile && !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Temporary upload file missing.'];
    }
    if ($allowLocalFile && !is_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Temporary upload file missing.'];
    }

    $fileSize = isset($file['size']) ? (int)$file['size'] : 0;
    if ($fileSize <= 0) {
        return ['ok' => false, 'message' => 'Uploaded file is empty.'];
    }
    if ($fileSize > GALLERY_MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'message' => 'File is too large. Max ' . galleryFormatBytes(GALLERY_MAX_UPLOAD_BYTES) . ' allowed per image.'];
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || empty($imageInfo['mime'])) {
        return ['ok' => false, 'message' => 'The uploaded file is not a valid image.'];
    }

    $mimeType = strtolower($imageInfo['mime']);
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return ['ok' => false, 'message' => 'Unsupported image format. Use JPEG, PNG, GIF, or WEBP.'];
    }

    if (!galleryEnsureStorageDirectory()) {
        return ['ok' => false, 'message' => 'Storage directory is not writable.'];
    }

    $sourceImage = galleryCreateSourceImage($file['tmp_name'], $mimeType);
    if (!$sourceImage) {
        return ['ok' => false, 'message' => 'Failed to read image for processing.'];
    }

    $sourceImage = galleryCorrectOrientation($sourceImage, $file['tmp_name'], $mimeType);

    if (function_exists('imagepalettetotruecolor')) {
        @imagepalettetotruecolor($sourceImage);
    }
    @imagealphablending($sourceImage, true);
    @imagesavealpha($sourceImage, true);

    $baseName = bin2hex(random_bytes(16));
    $webpName = $baseName . '.webp';
    $targetPath = rtrim(GALLERY_STORAGE_DIR, '/\\') . DIRECTORY_SEPARATOR . $webpName;

    $saved = @imagewebp($sourceImage, $targetPath, GALLERY_WEBP_QUALITY);
    @imagedestroy($sourceImage);

    if (!$saved) {
        return ['ok' => false, 'message' => 'Server failed to convert image.'];
    }

    $uploadDate = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("
        INSERT INTO gallery (gallery_id, upload_id, image_source, date, is_published)
        VALUES (?, ?, ?, ?, 0)
    ");

    if (!$stmt) {
        @unlink($targetPath);
        return ['ok' => false, 'message' => 'Failed to prepare gallery image insert query.'];
    }

    $stmt->bind_param('iiss', $galleryId, $userId, $targetPath, $uploadDate);
    if (!$stmt->execute()) {
        @unlink($targetPath);
        return ['ok' => false, 'message' => 'Failed to save image record in database.'];
    }

    return [
        'ok' => true,
        'image_id' => (int)$conn->insert_id,
        'image_source' => $targetPath,
        'filename' => $webpName,
    ];
}

function galleryUploadBatch($conn, $galleryId, $userId, $fileArrayKey)
{
    if (!isset($_FILES[$fileArrayKey])) {
        return [
            'ok' => false,
            'uploaded' => 0,
            'total' => 0,
            'errors' => ['No files uploaded.'],
        ];
    }

    $files = galleryNormalizeFiles($_FILES[$fileArrayKey]);
    if (empty($files)) {
        return [
            'ok' => false,
            'uploaded' => 0,
            'total' => 0,
            'errors' => ['No files uploaded.'],
        ];
    }

    $uploaded = 0;
    $errors = [];

    foreach ($files as $file) {
        $result = galleryUploadImage($conn, $galleryId, $userId, $file);
        if ($result['ok']) {
            $uploaded++;
        } else {
            $fileName = isset($file['name']) ? $file['name'] : 'unknown-file';
            $errors[] = $fileName . ': ' . $result['message'];
        }
    }

    return [
        'ok' => $uploaded > 0,
        'uploaded' => $uploaded,
        'total' => count($files),
        'errors' => $errors,
    ];
}

function galleryGetGalleryTitles($conn)
{
    $result = [];
    $query = "SELECT gallery_id, title FROM galleries ORDER BY gallery_id ASC";
    $dbResult = $conn->query($query);
    if (!$dbResult) {
        return $result;
    }

    while ($row = $dbResult->fetch_assoc()) {
        $result[] = $row;
    }

    return $result;
}

function galleryGetGalleryList($conn, $includePrivate)
{
    $result = [];
    $sql = "
        SELECT
            gl.gallery_id,
            gl.title AS gallery_title,
            gl.is_public,
            MAX(g.date) AS latest_date,
            SUM(CASE WHEN g.is_published = 1 THEN 1 ELSE 0 END) AS published_count,
            MIN(CASE WHEN g.is_published = 1 THEN g.image_source ELSE NULL END) AS thumbnail
        FROM galleries gl
        LEFT JOIN gallery g ON gl.gallery_id = g.gallery_id
    ";

    if (!$includePrivate) {
        $sql .= " WHERE gl.is_public = 1 ";
    }

    $sql .= "
        GROUP BY gl.gallery_id, gl.title, gl.is_public
        ORDER BY
            CASE WHEN MAX(g.date) IS NULL THEN 1 ELSE 0 END ASC,
            MAX(g.date) DESC,
            gl.gallery_id DESC
    ";

    $dbResult = $conn->query($sql);
    if (!$dbResult) {
        return $result;
    }

    while ($row = $dbResult->fetch_assoc()) {
        $result[] = $row;
    }

    return $result;
}

function galleryGetGalleryById($conn, $galleryId)
{
    $galleryId = (int)$galleryId;
    if ($galleryId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT gallery_id, title, is_public FROM galleries WHERE gallery_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $galleryId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

function galleryGetGalleryImages($conn, $galleryId, $includeUnpublished)
{
    $images = [];
    $galleryId = (int)$galleryId;
    if ($galleryId <= 0) {
        return $images;
    }

    $sql = "
        SELECT g.image_id, g.image_source, g.date, g.is_published, u.firstname, u.lastname
        FROM gallery g
        LEFT JOIN user u ON g.upload_id = u.user_id
        WHERE g.gallery_id = ?
    ";
    if (!$includeUnpublished) {
        $sql .= " AND g.is_published = 1 ";
    }
    $sql .= " ORDER BY g.date DESC, g.image_id DESC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $images;
    }

    $stmt->bind_param('i', $galleryId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        return $images;
    }

    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }

    return $images;
}

function galleryUpdateGalleryPublicState($conn, $galleryId, $isPublic)
{
    $galleryId = (int)$galleryId;
    $isPublic = (int)$isPublic === 1 ? 1 : 0;
    if ($galleryId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE galleries SET is_public = ? WHERE gallery_id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $isPublic, $galleryId);
    return $stmt->execute();
}

function gallerySetPublishedImages($conn, $galleryId, $publishedImages)
{
    $galleryId = (int)$galleryId;
    if ($galleryId <= 0) {
        return false;
    }

    $normalizedImages = [];
    if (is_array($publishedImages)) {
        foreach ($publishedImages as $imagePath) {
            $imagePath = trim((string)$imagePath);
            if ($imagePath === '') {
                continue;
            }
            $normalizedImages[$imagePath] = true;
        }
    }
    $normalizedImages = array_keys($normalizedImages);

    $conn->begin_transaction();
    try {
        $resetStmt = $conn->prepare("UPDATE gallery SET is_published = 0 WHERE gallery_id = ?");
        if (!$resetStmt) {
            throw new Exception('Failed to prepare reset statement.');
        }
        $resetStmt->bind_param('i', $galleryId);
        if (!$resetStmt->execute()) {
            throw new Exception('Failed to reset publish flags.');
        }

        if (!empty($normalizedImages)) {
            $placeholders = implode(',', array_fill(0, count($normalizedImages), '?'));
            $types = 'i' . str_repeat('s', count($normalizedImages));
            $sql = "UPDATE gallery SET is_published = 1 WHERE gallery_id = ? AND image_source IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare publish statement.');
            }

            $values = array_merge([$galleryId], $normalizedImages);
            if (!galleryBindParams($stmt, $types, $values)) {
                throw new Exception('Failed to bind publish params.');
            }
            if (!$stmt->execute()) {
                throw new Exception('Failed to update publish flags.');
            }
        }

        $conn->commit();
        return true;
    } catch (Exception $exception) {
        $conn->rollback();
        return false;
    }
}

function galleryDeleteFile($filePath)
{
    $filePath = trim((string)$filePath);
    if ($filePath === '') {
        return false;
    }

    $baseDir = rtrim(GALLERY_STORAGE_DIR, '/\\') . DIRECTORY_SEPARATOR;
    if (strpos($filePath, $baseDir) !== 0) {
        return false;
    }

    if (file_exists($filePath)) {
        return @unlink($filePath);
    }

    return false;
}

function galleryDeleteImageBySource($conn, $galleryId, $imageSource)
{
    $galleryId = (int)$galleryId;
    $imageSource = trim((string)$imageSource);
    if ($galleryId <= 0 || $imageSource === '') {
        return false;
    }

    $stmt = $conn->prepare("DELETE FROM gallery WHERE gallery_id = ? AND image_source = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $galleryId, $imageSource);
    $deleted = $stmt->execute() && $stmt->affected_rows > 0;
    if ($deleted) {
        galleryDeleteFile($imageSource);
    }

    return $deleted;
}

function galleryDeleteImageById($conn, $imageId)
{
    $imageId = (int)$imageId;
    if ($imageId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT image_source FROM gallery WHERE image_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        return false;
    }

    $row = $result->fetch_assoc();
    $imageSource = $row['image_source'];

    $deleteStmt = $conn->prepare("DELETE FROM gallery WHERE image_id = ?");
    if (!$deleteStmt) {
        return false;
    }
    $deleteStmt->bind_param('i', $imageId);
    $deleted = $deleteStmt->execute();

    if ($deleted) {
        galleryDeleteFile($imageSource);
    }

    return $deleted;
}

function galleryDeleteGallery($conn, $galleryId)
{
    $galleryId = (int)$galleryId;
    if ($galleryId <= 0) {
        return false;
    }

    $images = [];
    $stmt = $conn->prepare("SELECT image_source FROM gallery WHERE gallery_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $galleryId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $images[] = $row['image_source'];
        }
    }

    $conn->begin_transaction();
    try {
        $deleteImagesStmt = $conn->prepare("DELETE FROM gallery WHERE gallery_id = ?");
        if (!$deleteImagesStmt) {
            throw new Exception('Failed to prepare image delete statement.');
        }
        $deleteImagesStmt->bind_param('i', $galleryId);
        if (!$deleteImagesStmt->execute()) {
            throw new Exception('Failed to delete gallery images.');
        }

        $deleteGalleryStmt = $conn->prepare("DELETE FROM galleries WHERE gallery_id = ?");
        if (!$deleteGalleryStmt) {
            throw new Exception('Failed to prepare gallery delete statement.');
        }
        $deleteGalleryStmt->bind_param('i', $galleryId);
        if (!$deleteGalleryStmt->execute()) {
            throw new Exception('Failed to delete gallery.');
        }

        $conn->commit();
    } catch (Exception $exception) {
        $conn->rollback();
        return false;
    }

    foreach ($images as $imagePath) {
        galleryDeleteFile($imagePath);
    }

    return true;
}

function galleryHandleLegacyLogicRequest($conn)
{
    if (isset($_POST['new_gallery'])) {
        $user = galleryAuthorizeEditor($conn, false);
        if (!$user) {
            return true;
        }

        $title = isset($_POST['new_gallery_title']) ? $_POST['new_gallery_title'] : (isset($_POST['gallery_title']) ? $_POST['gallery_title'] : '');
        $createResult = galleryCreateGallery($conn, $title, 0);
        if (!$createResult['ok']) {
            http_response_code(400);
            echo $createResult['message'];
            return true;
        }

        $galleryId = (int)$createResult['gallery_id'];
        $fileKey = isset($_FILES['new_images']) ? 'new_images' : 'images';
        $uploadResult = galleryUploadBatch($conn, $galleryId, (int)$user['user_id'], $fileKey);

        if ($uploadResult['ok']) {
            http_response_code(200);
            echo "Success";
        } else {
            http_response_code(400);
            echo implode("\n", $uploadResult['errors']);
        }

        return true;
    }

    if (isset($_POST['add_to_gallery'])) {
        $user = galleryAuthorizeEditor($conn, false);
        if (!$user) {
            return true;
        }

        $galleryId = isset($_POST['existing_gallery_id']) ? (int)$_POST['existing_gallery_id'] : 0;
        if (!galleryGalleryExists($conn, $galleryId)) {
            http_response_code(400);
            echo "Selected gallery does not exist.";
            return true;
        }

        $fileKey = isset($_FILES['existing_images']) ? 'existing_images' : 'images';
        $uploadResult = galleryUploadBatch($conn, $galleryId, (int)$user['user_id'], $fileKey);

        if ($uploadResult['ok']) {
            http_response_code(200);
            echo "Success";
        } else {
            http_response_code(400);
            echo implode("\n", $uploadResult['errors']);
        }

        return true;
    }

    if (isset($_REQUEST['delete_photo'])) {
        $user = galleryAuthorizeEditor($conn, false);
        if (!$user) {
            return true;
        }

        $imageId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        if ($imageId > 0 && galleryDeleteImageById($conn, $imageId)) {
            header('Location:' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
            exit();
        }

        $GLOBALS['msg'] = 'Image not found in the database.';
        return true;
    }

    return false;
}

function galleryHandleProxyRequest($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        gallerySendJson(405, ['ok' => false, 'message' => 'Method not allowed.']);
    }

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    if ($action === '' && isset($_GET['action'])) {
        $action = trim((string)$_GET['action']);
    }

    if ($action === '') {
        if (galleryExceededPostLimit()) {
            gallerySendJson(413, ['ok' => false, 'message' => galleryBuildUploadLimitErrorMessage()]);
        }
        gallerySendJson(400, ['ok' => false, 'message' => 'Missing action.']);
    }

    if (in_array($action, ['upload_image', 'upload_batch', 'upload_chunk'], true)) {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');
    }

    if ($action === 'list_galleries') {
        galleryAuthorizeEditor($conn, true);
        $galleries = galleryGetGalleryTitles($conn);
        gallerySendJson(200, ['ok' => true, 'galleries' => $galleries]);
    }

    if ($action === 'create_gallery') {
        $user = galleryAuthorizeEditor($conn, true);
        $title = isset($_POST['title']) ? $_POST['title'] : (isset($_POST['new_gallery_title']) ? $_POST['new_gallery_title'] : '');
        $isPublic = isset($_POST['is_public']) ? (int)$_POST['is_public'] : 0;

        $createResult = galleryCreateGallery($conn, $title, $isPublic);
        if (!$createResult['ok']) {
            gallerySendJson(400, ['ok' => false, 'message' => $createResult['message']]);
        }

        gallerySendJson(201, [
            'ok' => true,
            'gallery_id' => (int)$createResult['gallery_id'],
            'user_id' => (int)$user['user_id'],
        ]);
    }

    if ($action === 'upload_image') {
        $user = galleryAuthorizeEditor($conn, true);
        $galleryId = isset($_POST['gallery_id']) ? (int)$_POST['gallery_id'] : 0;
        if (!galleryGalleryExists($conn, $galleryId)) {
            gallerySendJson(400, ['ok' => false, 'message' => 'Selected gallery does not exist.']);
        }

        if (isset($_FILES['image'])) {
            $file = $_FILES['image'];
        } else {
            $firstKey = function_exists('array_key_first') ? array_key_first($_FILES) : galleryFirstArrayKey($_FILES);
            $file = $firstKey !== null ? $_FILES[$firstKey] : null;
        }

        if (!$file) {
            if (galleryExceededPostLimit()) {
                gallerySendJson(413, ['ok' => false, 'message' => galleryBuildUploadLimitErrorMessage()]);
            }
            gallerySendJson(400, ['ok' => false, 'message' => 'No image file provided.']);
        }

        $uploadResult = galleryUploadImage($conn, $galleryId, (int)$user['user_id'], $file);
        if (!$uploadResult['ok']) {
            gallerySendJson(400, ['ok' => false, 'message' => $uploadResult['message']]);
        }

        gallerySendJson(200, [
            'ok' => true,
            'image_id' => (int)$uploadResult['image_id'],
            'image_source' => $uploadResult['image_source'],
            'filename' => $uploadResult['filename'],
        ]);
    }

    if ($action === 'upload_chunk') {
        $user = galleryAuthorizeEditor($conn, true);
        $galleryId = isset($_POST['gallery_id']) ? (int)$_POST['gallery_id'] : 0;
        if (!galleryGalleryExists($conn, $galleryId)) {
            gallerySendJson(400, ['ok' => false, 'message' => 'Selected gallery does not exist.']);
        }

        $uploadId = isset($_POST['upload_id']) ? gallerySanitizeUploadId($_POST['upload_id']) : '';
        $fileName = isset($_POST['file_name']) ? basename((string)$_POST['file_name']) : '';
        $fileSize = isset($_POST['file_size']) ? (int)$_POST['file_size'] : 0;
        $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : -1;
        $totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 0;

        if ($uploadId === '' || $fileName === '' || $fileSize <= 0 || $chunkIndex < 0 || $totalChunks <= 0 || $chunkIndex >= $totalChunks) {
            gallerySendJson(400, ['ok' => false, 'message' => 'Invalid chunk upload metadata.']);
        }

        if ($fileSize > GALLERY_MAX_UPLOAD_BYTES) {
            gallerySendJson(400, [
                'ok' => false,
                'message' => 'File exceeds gallery upload limit (' . galleryFormatBytes(GALLERY_MAX_UPLOAD_BYTES) . ').'
            ]);
        }

        if (!galleryEnsureChunkDirectory()) {
            gallerySendJson(500, ['ok' => false, 'message' => 'Upload temp directory is not writable.']);
        }

        if (isset($_FILES['chunk'])) {
            $chunkFile = $_FILES['chunk'];
        } elseif (isset($_FILES['image'])) {
            $chunkFile = $_FILES['image'];
        } else {
            $chunkFile = null;
        }

        if (!$chunkFile || !isset($chunkFile['error']) || (int)$chunkFile['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($chunkFile['tmp_name'])) {
            if (galleryExceededPostLimit()) {
                gallerySendJson(413, ['ok' => false, 'message' => galleryBuildUploadLimitErrorMessage()]);
            }
            gallerySendJson(400, ['ok' => false, 'message' => 'Invalid chunk upload payload.']);
        }

        $chunkDir = galleryGetChunkDirectory();
        $partPath = $chunkDir . $uploadId . '.part';
        $metaPath = $chunkDir . $uploadId . '.json';

        if ($chunkIndex === 0) {
            @unlink($partPath);
            @unlink($metaPath);
            $meta = [
                'gallery_id' => $galleryId,
                'user_id' => (int)$user['user_id'],
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'total_chunks' => $totalChunks,
            ];
            if (@file_put_contents($metaPath, json_encode($meta)) === false) {
                gallerySendJson(500, ['ok' => false, 'message' => 'Failed to initialize chunk upload.']);
            }
        } else {
            if (!is_file($partPath) || !is_file($metaPath)) {
                gallerySendJson(409, ['ok' => false, 'message' => 'Chunk upload state missing. Please retry upload.']);
            }
            $metaRaw = @file_get_contents($metaPath);
            $meta = $metaRaw ? json_decode($metaRaw, true) : null;
            if (!is_array($meta)
                || (int)$meta['gallery_id'] !== $galleryId
                || (int)$meta['user_id'] !== (int)$user['user_id']
                || (string)$meta['file_name'] !== $fileName
                || (int)$meta['file_size'] !== $fileSize
                || (int)$meta['total_chunks'] !== $totalChunks) {
                galleryCleanupChunkUpload($uploadId);
                gallerySendJson(409, ['ok' => false, 'message' => 'Chunk upload metadata mismatch. Please retry upload.']);
            }
        }

        $in = @fopen($chunkFile['tmp_name'], 'rb');
        $out = @fopen($partPath, 'ab');
        if (!$in || !$out) {
            if ($in) {
                fclose($in);
            }
            if ($out) {
                fclose($out);
            }
            galleryCleanupChunkUpload($uploadId);
            gallerySendJson(500, ['ok' => false, 'message' => 'Failed to write upload chunk.']);
        }

        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $receivedBytes = (int)@filesize($partPath);

        if ($chunkIndex < ($totalChunks - 1)) {
            gallerySendJson(200, [
                'ok' => true,
                'partial' => true,
                'received_bytes' => $receivedBytes,
                'total_bytes' => $fileSize,
            ]);
        }

        if ($receivedBytes !== $fileSize) {
            galleryCleanupChunkUpload($uploadId);
            gallerySendJson(400, ['ok' => false, 'message' => 'Chunk assembly failed: size mismatch.']);
        }

        $assembledFile = [
            'name' => $fileName,
            'tmp_name' => $partPath,
            'error' => UPLOAD_ERR_OK,
            'size' => $fileSize,
        ];
        $uploadResult = galleryUploadImageInternal($conn, $galleryId, (int)$user['user_id'], $assembledFile, true);
        galleryCleanupChunkUpload($uploadId);

        if (!$uploadResult['ok']) {
            gallerySendJson(400, ['ok' => false, 'message' => $uploadResult['message']]);
        }

        gallerySendJson(200, [
            'ok' => true,
            'image_id' => (int)$uploadResult['image_id'],
            'image_source' => $uploadResult['image_source'],
            'filename' => $uploadResult['filename'],
            'chunked' => true,
        ]);
    }

    if ($action === 'upload_batch') {
        $user = galleryAuthorizeEditor($conn, true);
        $galleryId = isset($_POST['gallery_id']) ? (int)$_POST['gallery_id'] : 0;
        $fileArrayKey = isset($_POST['file_array_key']) ? trim($_POST['file_array_key']) : 'images';

        if (!galleryGalleryExists($conn, $galleryId)) {
            gallerySendJson(400, ['ok' => false, 'message' => 'Selected gallery does not exist.']);
        }

        $uploadResult = galleryUploadBatch($conn, $galleryId, (int)$user['user_id'], $fileArrayKey);
        if (!$uploadResult['ok']) {
            gallerySendJson(400, $uploadResult);
        }

        gallerySendJson(200, $uploadResult);
    }

    if ($action === 'edit_gallery') {
        galleryAuthorizeEditor($conn, true);
        $galleryId = isset($_POST['gallery_id']) ? (int)$_POST['gallery_id'] : 0;
        if (!galleryGalleryExists($conn, $galleryId)) {
            gallerySendJson(400, ['ok' => false, 'message' => 'Gallery not found.']);
        }

        if (isset($_POST['delete_gallery']) && (string)$_POST['delete_gallery'] === '1') {
            $deleted = galleryDeleteGallery($conn, $galleryId);
            if (!$deleted) {
                gallerySendJson(500, ['ok' => false, 'message' => 'Failed to delete gallery.']);
            }
            gallerySendJson(200, ['ok' => true, 'deleted' => true]);
        }

        if (isset($_POST['is_public'])) {
            $updated = galleryUpdateGalleryPublicState($conn, $galleryId, (int)$_POST['is_public']);
            if (!$updated) {
                gallerySendJson(500, ['ok' => false, 'message' => 'Failed to update gallery visibility.']);
            }
        }

        if (isset($_POST['published']) && is_array($_POST['published'])) {
            $publishUpdated = gallerySetPublishedImages($conn, $galleryId, $_POST['published']);
            if (!$publishUpdated) {
                gallerySendJson(500, ['ok' => false, 'message' => 'Failed to update published images.']);
            }
        }

        if (isset($_POST['delete_image']) && $_POST['delete_image'] !== '') {
            $deleted = galleryDeleteImageBySource($conn, $galleryId, $_POST['delete_image']);
            if (!$deleted) {
                gallerySendJson(400, ['ok' => false, 'message' => 'Failed to delete image.']);
            }
        }

        gallerySendJson(200, ['ok' => true]);
    }

    gallerySendJson(400, ['ok' => false, 'message' => 'Unknown action.']);
}
