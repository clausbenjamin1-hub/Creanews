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

$root = __DIR__;
$srcRoot = $root . DIRECTORY_SEPARATOR . 'Image';
$dstRoot = $root . DIRECTORY_SEPARATOR . 'subImage';

if (!is_dir($srcRoot)) {
    http_response_code(400);
    echo json_encode(['error' => 'Source folder Image/ not found']);
    exit;
}

if (!is_dir($dstRoot) && !mkdir($dstRoot, 0777, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create subImage directory']);
    exit;
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$maxW = 1600;
$maxH = 1600;

$total = 0;
$resized = 0;
$skipped = 0;
$errors = [];

$finfo = new finfo(FILEINFO_MIME_TYPE);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $srcPath = $file->getPathname();
    $mime = $finfo->file($srcPath) ?: '';
    if (!isset($allowed[$mime])) {
        continue; // not an image we handle
    }
    $total++;

    // Compute relative path
    $relPath = substr($srcPath, strlen($srcRoot) + 1);
    $relDir  = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, dirname($relPath));
    $base    = pathinfo($relPath, PATHINFO_FILENAME);
    $extMap  = $allowed[$mime];
    $dstDir  = rtrim($dstRoot . DIRECTORY_SEPARATOR . ($relDir === '.' ? '' : $relDir), DIRECTORY_SEPARATOR);
    $dstPath = $dstDir . DIRECTORY_SEPARATOR . $base . '.' . $extMap;

    if (!is_dir($dstDir) && !mkdir($dstDir, 0777, true)) {
        $errors[] = "Cannot create directory: $dstDir";
        continue;
    }

    // Load source
    $srcImg = null;
    try {
        switch ($mime) {
            case 'image/jpeg':
                $srcImg = imagecreatefromjpeg($srcPath);
                break;
            case 'image/png':
                $srcImg = imagecreatefrompng($srcPath);
                break;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    throw new RuntimeException('WEBP not supported by GD');
                }
                $srcImg = imagecreatefromwebp($srcPath);
                break;
        }
    } catch (Throwable $t) {
        $errors[] = "Failed to read: $relPath - " . $t->getMessage();
        $skipped++;
        continue;
    }

    if (!$srcImg) {
        $errors[] = "Failed to read: $relPath";
        $skipped++;
        continue;
    }

    $srcW = imagesx($srcImg);
    $srcH = imagesy($srcImg);

    $scale = min($maxW / max(1, $srcW), $maxH / max(1, $srcH), 1.0);
    $dstW = (int)floor($srcW * $scale);
    $dstH = (int)floor($srcH * $scale);

    // If already within bounds, copy without resample
    if ($scale >= 1.0) {
        // still write out in destination format to unify
        $dstImg = imagecreatetruecolor($srcW, $srcH);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
            imagefilledrectangle($dstImg, 0, 0, $srcW, $srcH, $transparent);
        }
        imagecopy($dstImg, $srcImg, 0, 0, 0, 0, $srcW, $srcH);
    } else {
        $dstImg = imagecreatetruecolor($dstW, $dstH);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
            imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $transparent);
        }
        if (!imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH)) {
            imagedestroy($srcImg);
            imagedestroy($dstImg);
            $errors[] = "Failed to resize: $relPath";
            $skipped++;
            continue;
        }
    }

    // Save
    $ok = false;
    switch ($mime) {
        case 'image/jpeg':
            $ok = imagejpeg($dstImg, $dstPath, 82);
            break;
        case 'image/png':
            $ok = imagepng($dstImg, $dstPath, 6);
            break;
        case 'image/webp':
            if (!function_exists('imagewebp')) {
                $errors[] = 'WEBP save not supported by GD';
                $ok = false;
                break;
            }
            $ok = imagewebp($dstImg, $dstPath, 82);
            break;
    }

    imagedestroy($srcImg);
    imagedestroy($dstImg);

    if ($ok) {
        $resized++;
    } else {
        $errors[] = "Failed to save: $relPath";
        $skipped++;
    }
}

echo json_encode([
    'success' => true,
    'total' => $total,
    'resized' => $resized,
    'skipped' => $skipped,
    'errors' => $errors,
]);
