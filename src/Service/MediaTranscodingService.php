<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaTranscodingService
{
    private ?Cloudinary $cloudinary = null;
    private string $uploadDir;

    public function __construct(
        private ?string $cloudinaryCloudName,
        private ?string $cloudinaryApiKey,
        private ?string $cloudinaryApiSecret,
        private bool $useCloudinary,
        private ?LoggerInterface $logger = null
    ) {
        $this->uploadDir = __DIR__ . '/../../public/uploads/';
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        if ($this->useCloudinary && $this->cloudinaryCloudName && $this->cloudinaryApiKey && $this->cloudinaryApiSecret) {
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => $this->cloudinaryCloudName,
                    'api_key' => $this->cloudinaryApiKey,
                    'api_secret' => $this->cloudinaryApiSecret,
                ],
                'url' => [
                    'secure' => true,
                ],
            ]);
            $this->cloudinary = new Cloudinary();
        }
    }

    public function uploadGif(UploadedFile $file): ?string
    {
        $filename = uniqid('gif_', true) . '.gif';
        $filepath = $this->uploadDir . $filename;

        try {
            $file->move($this->uploadDir, $filename);

            if ($this->cloudinary) {
                $result = $this->cloudinary->uploadApi()->upload($filepath, [
                    'resource_type' => 'image',
                    'format' => 'gif',
                ]);
                unlink($filepath);
                return $result['secure_url'];
            }

            return '/uploads/' . $filename;
        } catch (\Exception $e) {
            $this->logger?->error('GIF upload error: ' . $e->getMessage());
            return null;
        }
    }

    public function convertVideoToGif(UploadedFile $videoFile): ?string
    {
        if (!function_exists('exec')) {
            $this->logger?->warning('exec() function not available, cannot convert video to GIF');
            return null;
        }

        $videoPath = $videoFile->getPathname();
        $outputFilename = uniqid('gif_', true) . '.gif';
        $outputPath = $this->uploadDir . $outputFilename;

        try {
            // Convert video to GIF using ffmpeg
            $command = sprintf(
                'ffmpeg -i %s -vf "fps=10,scale=320:-1:flags=lanczos" -y %s 2>&1',
                escapeshellarg($videoPath),
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($outputPath)) {
                $this->logger?->error('FFmpeg conversion failed: ' . implode("\n", $output));
                return null;
            }

            if ($this->cloudinary) {
                $result = $this->cloudinary->uploadApi()->upload($outputPath, [
                    'resource_type' => 'image',
                    'format' => 'gif',
                ]);
                unlink($outputPath);
                return $result['secure_url'];
            }

            return '/uploads/' . $outputFilename;
        } catch (\Exception $e) {
            $this->logger?->error('Video to GIF conversion error: ' . $e->getMessage());
            return null;
        }
    }

    public function deleteMedia(string $url): bool
    {
        if ($this->cloudinary && str_contains($url, 'cloudinary.com')) {
            try {
                $publicId = $this->extractPublicId($url);
                $this->cloudinary->uploadApi()->destroy($publicId);
                return true;
            } catch (\Exception $e) {
                $this->logger?->error('Cloudinary delete error: ' . $e->getMessage());
                return false;
            }
        }

        $filepath = $this->uploadDir . basename($url);
        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return false;
    }

    private function extractPublicId(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $pathParts = explode('/', $path);
        $filename = end($pathParts);
        return pathinfo($filename, PATHINFO_FILENAME);
    }
}

