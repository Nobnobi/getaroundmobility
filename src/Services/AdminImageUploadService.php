<?php
namespace App\Services;

class AdminImageUploadService
{
    private const MAX_BYTES = 5242880;

    private const ALLOWED_MIME_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public function store(array $imageFile, string $prefix, string $emptyMessage = 'Image upload failed.'): ?string
    {
        $errorCode = (int)($imageFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($emptyMessage);
        }

        $tmpFile = (string)($imageFile['tmp_name'] ?? '');
        if ($tmpFile === '' || !is_uploaded_file($tmpFile)) {
            throw new \RuntimeException('Invalid uploaded image.');
        }

        $size = (int)($imageFile['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('Please upload an image smaller than 5 MB.');
        }

        $originalName = (string)($imageFile['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_MIME_TYPES[$extension])) {
            throw new \RuntimeException('Please upload JPG, PNG, or WEBP images only.');
        }

        $mimeType = $this->detectMimeType($tmpFile);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES[$extension], true)) {
            throw new \RuntimeException('The uploaded file type does not match the image extension.');
        }

        $imageInfo = @getimagesize($tmpFile);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new \RuntimeException('The uploaded file is not a valid image.');
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/img/uploads';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Unable to create the image upload folder.');
        }

        $safePrefix = preg_replace('/[^a-z0-9-]+/i', '-', trim($prefix)) ?: 'image';
        $safePrefix = strtolower(trim($safePrefix, '-')) ?: 'image';
        $fileName = $safePrefix . '-' . time() . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($tmpFile, $destination)) {
            throw new \RuntimeException('Unable to save the uploaded image.');
        }

        @chmod($destination, 0644);

        return '/img/uploads/' . $fileName;
    }

    private function detectMimeType(string $tmpFile): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpFile);
        return is_string($mimeType) ? $mimeType : '';
    }
}
