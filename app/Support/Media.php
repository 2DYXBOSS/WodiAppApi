<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class Media
{
    /**
     * URL publique d'une image : URL absolue servie telle quelle ; chemin stocké
     * sur le disque média (`media/…`) converti en URL absolue ; sinon renvoyé tel quel
     * (chemin d'image locale du site vitrine).
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://', 'demo:'])) {
            return $path;
        }
        if (Str::startsWith($path, 'media/')) {
            return Storage::disk(config('wodi.media.disk'))->url($path);
        }

        return $path;
    }

    /** Chemin stocké correspondant à une URL produite par ce serveur (pour suppression). */
    public static function storedPath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (Str::startsWith($url, 'media/')) {
            return $url;
        }
        $base = rtrim(Storage::disk(config('wodi.media.disk'))->url(''), '/');

        return Str::startsWith($url, $base.'/media/') ? Str::after($url, $base.'/') : null;
    }
}
