<?php
declare(strict_types=1);

$imagesDir = __DIR__ . DIRECTORY_SEPARATOR . 'saved_images';
$imagesUrlPath = 'saved_images';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function resolveImageUrl(string $rawUrl): string
{
    $rawUrl = trim($rawUrl);
    if ($rawUrl === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $rawUrl)) {
        return $rawUrl;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $normalized = ltrim($rawUrl, '/');

    return $scheme . '://' . $host . ($scriptDir ? $scriptDir . '/' : '/') . $normalized;
}

function detectImageExtension(string $binary): string
{
    if (strncmp($binary, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }

    if (strncmp($binary, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return 'png';
    }

    if (strncmp($binary, 'GIF87a', 6) === 0 || strncmp($binary, 'GIF89a', 6) === 0) {
        return 'gif';
    }

    if (strncmp($binary, 'BM', 2) === 0) {
        return 'bmp';
    }

    if (strlen($binary) > 12 && strncmp(substr($binary, 0, 4), 'RIFF', 4) === 0 && strncmp(substr($binary, 8, 4), 'WEBP', 4) === 0) {
        return 'webp';
    }

    return 'jpg';
}

function fetchBinary(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not start cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Image-Saver/1.0'
            ],
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400 || $status === 0) {
            throw new RuntimeException('Image URL returned HTTP ' . $status . '.');
        }

        return $result;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => "User-Agent: Image-Saver/1.0\r\n",
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        throw new RuntimeException('Could not download image.');
    }

    return $result;
}

function listSavedImages(string $imagesDir, string $imagesUrlPath): array
{
    if (!is_dir($imagesDir)) {
        return [];
    }

    $items = scandir($imagesDir);
    if ($items === false) {
        return [];
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $images = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $imagesDir . DIRECTORY_SEPARATOR . $item;
        if (!is_file($fullPath)) {
            continue;
        }

        $ext = strtolower((string) pathinfo($item, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }

        $images[] = [
            'file' => $item,
            'url' => $imagesUrlPath . '/' . rawurlencode($item),
            'time' => filemtime($fullPath) ?: 0,
        ];
    }

    usort($images, static function (array $a, array $b): int {
        return $b['time'] <=> $a['time'];
    });

    return array_map(static function (array $entry): array {
        return [
            'file' => $entry['file'],
            'url' => $entry['url'],
        ];
    }, $images);
}

$api = $_GET['api'] ?? '';

if ($api === 'save-image' && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'GET'], true)) {
    $rawImageUrl = '';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        $rawImageUrl = is_array($payload) ? (string) ($payload['imageUrl'] ?? '') : '';
    }

    if ($rawImageUrl === '') {
        $rawImageUrl = (string) ($_GET['imageUrl'] ?? '');
    }

    if ($rawImageUrl === '') {
        jsonResponse(['ok' => false, 'message' => 'imageUrl is required.'], 400);
    }

    $resolvedUrl = resolveImageUrl($rawImageUrl);
    if ($resolvedUrl === '') {
        jsonResponse(['ok' => false, 'message' => 'Invalid image URL.'], 400);
    }

    try {
        if (!is_dir($imagesDir) && !mkdir($imagesDir, 0777, true) && !is_dir($imagesDir)) {
            throw new RuntimeException('Could not create saved_images folder.');
        }

        $binary = fetchBinary($resolvedUrl);
        if ($binary === '') {
            throw new RuntimeException('Downloaded file is empty.');
        }

        $extension = detectImageExtension($binary);
        $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = $imagesDir . DIRECTORY_SEPARATOR . $fileName;

        $written = file_put_contents($targetPath, $binary);
        if ($written === false) {
            throw new RuntimeException('Could not save file.');
        }

        jsonResponse([
            'ok' => true,
            'message' => 'Image saved successfully.',
            'saved' => [
                'file' => $fileName,
                'url' => $imagesUrlPath . '/' . rawurlencode($fileName),
            ],
            'images' => listSavedImages($imagesDir, $imagesUrlPath),
        ]);
    } catch (Throwable $error) {
        jsonResponse(['ok' => false, 'message' => $error->getMessage()], 500);
    }
}

if ($api === 'list-images' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse([
        'ok' => true,
        'images' => listSavedImages($imagesDir, $imagesUrlPath),
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Image Saver</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 18px;
            background: #f4f6fb;
            color: #16213a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dbe3f0;
            border-radius: 14px;
            padding: 16px;
        }

        h1 {
            margin: 0 0 14px;
            font-size: 1.35rem;
        }

        .row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.96rem;
        }

        button {
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #ffffff;
            padding: 10px 14px;
            font-size: 0.96rem;
            cursor: pointer;
        }

        button:disabled {
            opacity: 0.65;
            cursor: wait;
        }

        .status {
            font-size: 0.93rem;
            color: #475569;
            margin-bottom: 14px;
            min-height: 20px;
        }

        .status.error {
            color: #b91c1c;
        }

        .status.success {
            color: #047857;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }

        .card {
            border: 1px solid #dbe3f0;
            border-radius: 10px;
            padding: 8px;
            background: #f8fafc;
        }

        .card img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            border-radius: 8px;
            display: block;
            margin-bottom: 7px;
        }

        .card-name {
            font-size: 0.78rem;
            color: #334155;
            word-break: break-word;
        }

        .empty {
            color: #64748b;
            font-size: 0.94rem;
            padding: 6px 2px;
        }

        @media (max-width: 640px) {
            .row {
                flex-direction: column;
            }

            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>Simple Image Saver API Demo</h1>

        <div class="row">
            <input id="imageUrl" type="text" placeholder="Example: CaptchaImage.axd?guid=a2d55eb2-7bec-4638-907e-e0d344ce34fd" autocomplete="off">
            <button id="saveBtn">Save Image</button>
        </div>

        <div id="status" class="status">Enter an image URL and click Save Image.</div>
        <div id="gallery" class="gallery"></div>
    </main>

    <script>
        const imageUrlInput = document.getElementById('imageUrl');
        const saveBtn = document.getElementById('saveBtn');
        const status = document.getElementById('status');
        const gallery = document.getElementById('gallery');

        function setStatus(message, kind = '') {
            status.textContent = message;
            status.className = kind ? `status ${kind}` : 'status';
        }

        function renderImages(images) {
            if (!Array.isArray(images) || images.length === 0) {
                gallery.innerHTML = '<div class="empty">No saved images yet.</div>';
                return;
            }

            gallery.innerHTML = images.map((item) => {
                const safeFile = String(item.file || 'image');
                const safeUrl = String(item.url || '');
                return `
                    <div class="card">
                        <img src="${safeUrl}" alt="${safeFile}" loading="lazy">
                        <div class="card-name">${safeFile}</div>
                    </div>
                `;
            }).join('');
        }

        async function loadImages() {
            try {
                const response = await fetch('?api=list-images');
                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.message || 'Could not list images.');
                }
                renderImages(data.images || []);
            } catch (error) {
                setStatus(`Load error: ${error.message}`, 'error');
            }
        }

        async function saveImage() {
            const imageUrl = imageUrlInput.value.trim();

            if (!imageUrl) {
                setStatus('Please enter an image URL first.', 'error');
                return;
            }

            saveBtn.disabled = true;
            setStatus('Saving image...', '');

            try {
                const response = await fetch('?api=save-image', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ imageUrl })
                });

                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.message || 'Save failed.');
                }

                renderImages(data.images || []);
                setStatus(data.message || 'Image saved.', 'success');
            } catch (error) {
                setStatus(`Save error: ${error.message}`, 'error');
            } finally {
                saveBtn.disabled = false;
            }
        }

        saveBtn.addEventListener('click', saveImage);
        imageUrlInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                saveImage();
            }
        });

        loadImages();
    </script>
</body>
</html>
