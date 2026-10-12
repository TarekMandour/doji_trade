<?php

namespace App\\Services\\Crypto;

use Illuminate\\Support\\Facades\\Storage;
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
        $disk->makeDirectory(dirname($path));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(5));

        if (!$disk->put($temporary, $json)) {
            throw new RuntimeException("Unable to write temporary JSON file: {$temporary}");
        }

        if (!$disk->move($temporary, $path)) {
            $disk->delete($temporary);
            throw new RuntimeException("Unable to atomically replace JSON file: {$path}");
        }
    }

    public function deleteScanFile(string $path): void
    {
        if (str_starts_with($path, 'crypto/scans/') && str_ends_with($path, '.json')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function scanFilePath(string $timestamp, int $sequence): string
    {
        return sprintf('crypto/scans/%s_%d.json', $timestamp, $sequence);
    }
}
