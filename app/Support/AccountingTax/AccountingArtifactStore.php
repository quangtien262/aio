<?php

namespace App\Support\AccountingTax;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AccountingArtifactStore
{
    public const DISK = 'accounting_private';

    /**
     * @param  resource|string  $contents
     * @return array{disk:string,path:string,checksum:string,byte_size:int}
     */
    public function putAtomically(string $path, mixed $contents): array
    {
        $disk = $this->disk();
        $temporaryPath = $path.'.tmp-'.bin2hex(random_bytes(8));

        try {
            if (! $disk->put($temporaryPath, $contents)) {
                throw new RuntimeException('Không thể ghi file kế toán tạm thời.');
            }

            $checksum = $this->checksum($temporaryPath);
            $byteSize = $disk->size($temporaryPath);

            if ($disk->exists($path) || ! $disk->move($temporaryPath, $path)) {
                throw new RuntimeException('Không thể hoàn tất file kế toán bất biến.');
            }

            return [
                'disk' => self::DISK,
                'path' => $path,
                'checksum' => $checksum,
                'byte_size' => $byteSize,
            ];
        } finally {
            if ($disk->exists($temporaryPath)) {
                $disk->delete($temporaryPath);
            }
        }
    }

    /**
     * @return array{disk:string,path:string,checksum:string,byte_size:int}
     */
    public function copyImmutable(string $sourcePath, string $destinationPath, ?string $expectedChecksum = null): array
    {
        $disk = $this->disk();

        if (! $disk->exists($sourcePath)) {
            throw new RuntimeException('File nguồn không còn tồn tại trong vùng lưu trữ kế toán.');
        }

        $sourceChecksum = $this->checksum($sourcePath);

        if ($expectedChecksum !== null && ! hash_equals($expectedChecksum, $sourceChecksum)) {
            throw new RuntimeException('Checksum file nguồn không hợp lệ.');
        }

        $stream = $disk->readStream($sourcePath);

        if (! is_resource($stream)) {
            throw new RuntimeException('Không thể đọc file nguồn kế toán.');
        }

        try {
            return $this->putAtomically($destinationPath, $stream);
        } finally {
            fclose($stream);
        }
    }

    public function checksum(string $path): string
    {
        $stream = $this->disk()->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Không thể kiểm tra checksum file kế toán.');
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    public function existsWithChecksum(string $path, ?string $checksum): bool
    {
        return $checksum !== null
            && $this->disk()->exists($path)
            && hash_equals($checksum, $this->checksum($path));
    }

    public function delete(string $path): void
    {
        if ($this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }
}
