<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing image']);
    exit;
}

$err = (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload error', 'code' => $err]);
    exit;
}

$tmpPath = (string)($_FILES['image']['tmp_name'] ?? '');
$origName = (string)($_FILES['image']['name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid upload']);
    exit;
}

// Detect MIME using finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmpPath) ?: '';
$allowed = [
    'image/jpeg' => '.jpg',
    'image/png'  => '.png',
    'image/webp' => '.webp',
];
if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported image type', 'mime' => $mime]);
    exit;
}

// Ensure subImage/ directory exists
$targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'subImage' . DIRECTORY_SEPARATOR;
if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create subImage directory']);
    exit;
}

// Desired filename provided by user (without extension expected)
$requested = (string)($_POST['filename'] ?? $origName);
$overwrite = isset($_POST['overwrite']) && (string)$_POST['overwrite'] === '1';

// Sanitize requested filename
$requested = preg_replace('/[\\\/:*?"<>|]/', '', $requested) ?: 'image';
$requested = preg_replace('/\s+/', '_', $requested);
$requested = preg_replace('/\.+/', '.', $requested); // collapse multiple dots

// Split stem (ignore any extension the user might have put)
$extByMime = $allowed[$mime];
$stem = $requested;
$dotPos = strrpos($requested, '.');
if ($dotPos !== false) {
    $stem = substr($requested, 0, $dotPos);
}
$stem = preg_replace('/[^a-zA-Z0-9_\-]/', '', $stem) ?: 'image';
$stem = substr($stem, 0, 40);
$finalName = $stem . $extByMime;
$destPath = $targetDir . $finalName;

if (!$overwrite) {
    if (file_exists($destPath)) {
        $ts = date('Ymd_His');
        $rand = bin2hex(random_bytes(3));
        $finalName = $stem . '_' . $ts . '_' . $rand . $extByMime;
        $destPath = $targetDir . $finalName;
    }
} else {
    if (file_exists($destPath)) {
        @unlink($destPath);
    }
}

// Load the uploaded image into GD
switch ($mime) {
    case 'image/jpeg':
        $srcImg = imagecreatefromjpeg($tmpPath);
        break;
    case 'image/png':
        $srcImg = imagecreatefrompng($tmpPath);
        break;
    case 'image/webp':
        if (!function_exists('imagecreatefromwebp')) {
            http_response_code(500);
            echo json_encode(['error' => 'WEBP not supported by GD on server']);
            exit;
        }
        $srcImg = imagecreatefromwebp($tmpPath);
        break;
    default:
        $srcImg = false;
}

if (!$srcImg) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to read image']);
    exit;
}

$srcW = imagesx($srcImg);
$srcH = imagesy($srcImg);

// Best-fit for web: cap at 1600x1600, preserve aspect ratio
$maxW = 1600;
$maxH = 1600;
$scale = min($maxW / max(1, $srcW), $maxH / max(1, $srcH), 1.0);
$dstW = (int)floor($srcW * $scale);
$dstH = (int)floor($srcH * $scale);

$dstImg = imagecreatetruecolor($dstW, $dstH);

// Preserve transparency for PNG and WEBP
if ($mime === 'image/png' || $mime === 'image/webp') {
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
    imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $transparent);
}

if (!imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH)) {
    imagedestroy($srcImg);
    imagedestroy($dstImg);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to resize image']);
    exit;
}

// Save with reasonable quality
$ok = false;
switch ($mime) {
    case 'image/jpeg':
        $ok = imagejpeg($dstImg, $destPath, 82); // quality 0-100
        break;
    case 'image/png':
        $ok = imagepng($dstImg, $destPath, 6); // compression 0-9
        break;
    case 'image/webp':
        if (!function_exists('imagewebp')) {
            imagedestroy($srcImg);
            imagedestroy($dstImg);
            http_response_code(500);
            echo json_encode(['error' => 'WEBP save not supported by GD on server']);
            exit;
        }
        $ok = imagewebp($dstImg, $destPath, 82); // quality 0-100
        break;
}

imagedestroy($srcImg);
imagedestroy($dstImg);

if (!$ok || !file_exists($destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save resized image']);
    exit;
}

$url = 'subImage/' . $finalName;
echo json_encode([
    'success' => true,
    'url' => $url,
]);
