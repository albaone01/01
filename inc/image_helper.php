<?php
/**
 * Image upload helper (hardened)
 * - strict MIME validation with finfo + getimagesize
 * - UUID filename
 * - resize/compress to webp by default
 * - reject dangerous payload (php/script/svg/xml entities)
 */

if (!function_exists('safe_uuid_v4')) {
    function safe_uuid_v4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('contains_dangerous_upload_payload')) {
    function contains_dangerous_upload_payload(string $path): bool {
        $raw = file_get_contents($path, false, null, 0, 4096);
        if ($raw === false) return true;
        $lower = strtolower($raw);
        $blocked = ['<?php', '<script', '<svg', '<!doctype', '<!entity', '<?xml', 'onload=', 'onerror='];
        foreach ($blocked as $sig) {
            if (strpos($lower, $sig) !== false) return true;
        }
        return false;
    }
}

if (!function_exists('process_upload_image')) {
    function process_upload_image(array $file, string $destDir, array $opt = []): string {
        $defaults = [
            'max_width'  => 1200,
            'max_height' => 1200,
            'quality'    => 82,
            'max_bytes'  => 5 * 1024 * 1024,
            'prefix'     => 'img_',
            'force_webp' => true,
        ];
        $opt = array_merge($defaults, $opt);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Upload gagal: kode ' . ($file['error'] ?? 'unknown'));
        }
        if (($file['size'] ?? 0) <= 0) {
            throw new Exception('File upload kosong');
        }
        if (($file['size'] ?? 0) > $opt['max_bytes']) {
            throw new Exception('Ukuran file melebihi batas ' . round($opt['max_bytes'] / 1024 / 1024, 1) . 'MB');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Upload file tidak valid');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            throw new Exception('Format gambar tidak didukung');
        }

        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo || !isset($imgInfo['mime']) || !in_array($imgInfo['mime'], $allowed, true)) {
            throw new Exception('File bukan gambar valid');
        }

        // Hard block suspicious payload and SVG/script/xml entity tricks.
        if (contains_dangerous_upload_payload($file['tmp_name'])) {
            throw new Exception('File gambar terindikasi berbahaya');
        }

        switch ($mime) {
            case 'image/jpeg':
                $src = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $src = @imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/webp':
                $src = @imagecreatefromwebp($file['tmp_name']);
                break;
            case 'image/gif':
                $src = @imagecreatefromgif($file['tmp_name']);
                break;
            default:
                $src = false;
                break;
        }
        if (!$src) throw new Exception('Gagal membaca gambar');

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($src);
            throw new Exception('Dimensi gambar tidak valid');
        }

        $scale = min(1, $opt['max_width'] / $w, $opt['max_height'] / $h);
        if ($scale < 1) {
            $newW = max(1, (int)floor($w * $scale));
            $newH = max(1, (int)floor($h * $scale));
            $dst = imagecreatetruecolor($newW, $newH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                imagedestroy($src);
                throw new Exception('Gagal membuat folder upload');
            }
        }

        $ext = $opt['force_webp'] ? '.webp' : '.jpg';
        $basename = $opt['prefix'] . safe_uuid_v4() . $ext;
        $savePath = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename;

        $ok = $opt['force_webp']
            ? @imagewebp($src, $savePath, (int)$opt['quality'])
            : @imagejpeg($src, $savePath, 90);

        imagedestroy($src);
        if (!$ok || !file_exists($savePath)) {
            throw new Exception('Gagal menyimpan gambar');
        }

        return $basename;
    }
}
