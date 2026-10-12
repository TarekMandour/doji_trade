<?php

namespace App\Services\Crypto;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CryptoJsonStorageService
{
    private const DISK = 'local';

    public function read(string $path): ?array
    {
        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($path)) return null;

        $decoded = json_decode($disk->get($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid JSON file: {$path}");
        }

        return $decoded;
    }

    public function write(string $path, array $data): void
    {
        $disk = Storage::disk(self::DISK);
        $directory = dirname($path);
        $disk->makeDirectory($directory);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(5));

        if (!$disk->put($temporary, $json)) {
            throw new RuntimeException("Unable to write temporary JSON file: {$temporary}");
        }

        if (!$disk->move($temporary, $path)) {
            $disk->delete($temporary);
            throw new RuntimeException("Unable to replace JSON file: {$path}");
        }
    }

    public function deleteDirectory(string $path): void
    {
        if ($path !== '' && str_starts_with($path, 'crypto/scans/')) {
            Storage::disk(self::DISK)->deleteDirectory($path);
        }
    }

    public function candlePath(string $symbol, string $interval, string $date): string
    {
        return sprintf('crypto/candles/%s/%s/%s.json', strtoupper($symbol), $interval, $date);
    }

    public function scanDirectory(string $uuid): string
    {
        return 'crypto/scans/' . $uuid;
    }
}
