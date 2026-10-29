<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CloudinaryImageService
{
    public function upload(UploadedFile $file, string $folder): string
    {
        $result = $this->cloudinary()->uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder,
        ]);

        return $result['secure_url'];
    }

    public function delete(?string $image): void
    {
        if (!$image) {
            return;
        }

        $imageUrl = parse_url($image);
        $cloudinaryHost = 'res.cloudinary.com';

        if (($imageUrl['host'] ?? null) !== $cloudinaryHost) {
            Storage::disk('public')->delete($image);

            return;
        }

        $path = $imageUrl['path'] ?? '';
        $uploadMarker = '/image/upload/';
        $uploadPosition = strpos($path, $uploadMarker);

        if ($uploadPosition === false) {
            return;
        }

        $publicId = substr($path, $uploadPosition + strlen($uploadMarker));
        $publicId = preg_replace('#^v\d+/#', '', $publicId);
        $publicId = preg_replace('/\.[^.\/]+$/', '', $publicId);

        if ($publicId !== '') {
            $this->cloudinary()->uploadApi()->destroy($publicId);
        }
    }

    private function cloudinary(): Cloudinary
    {
        return new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }
}
