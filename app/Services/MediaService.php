<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Stockage des images publiques (galerie, équipe, prestations, logo, bannière, avatars). */
class MediaService
{
    public function store(UploadedFile $file, string $folder): string
    {
        $mime = $file->getMimeType();
        if (! in_array($mime, config('wodi.media.mimes'), true) || @getimagesize($file->getRealPath()) === false) {
            throw ApiException::unprocessable('Format non accepté : JPEG, PNG ou WebP uniquement.', ['file' => 'Format non accepté.']);
        }
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $path = $file->storeAs('media/'.$folder.'/'.now()->format('Y/m'), Str::uuid().'.'.$extension, ['disk' => config('wodi.media.disk')]);

        return Media::url($path);
    }

    /** Supprime une ancienne image gérée par ce serveur (les URL externes sont ignorées). */
    public function deleteIfManaged(?string $url): void
    {
        if ($path = Media::storedPath($url)) {
            Storage::disk(config('wodi.media.disk'))->delete($path);
        }
    }
}
