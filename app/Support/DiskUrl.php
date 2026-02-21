<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiskUrl
{
    public static function fromPath(string $disk, string $path): string
    {
        $normalizedPath = ltrim($path, '/');
        $url = Storage::disk($disk)->url($normalizedPath);

        if ($url === '' || $url === $normalizedPath) {
            return $url;
        }

        $driver = (string) config(sprintf('filesystems.disks.%s.driver', $disk));

        if ($driver !== 'local') {
            return $url;
        }

        return self::toRelativePath($url);
    }

    private static function toRelativePath(string $url): string
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return $url;
        }

        $path = $parts['path'] ?? '';

        if ($path === '') {
            return $url;
        }

        if (isset($parts['query']) && is_string($parts['query'])) {
            $path .= sprintf('?%s', $parts['query']);
        }

        if (isset($parts['fragment']) && is_string($parts['fragment'])) {
            $path .= sprintf('#%s', $parts['fragment']);
        }

        return $path;
    }
}
