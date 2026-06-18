<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Almacenamiento de archivos del módulo de Requerimientos.
 *
 * El disco se decide por config('filesystems.refund_disk') (env REFUND_DISK):
 *   - dev: 'public'  (local, se ve por /storage)
 *   - prod: 'spaces' (DigitalOcean, archivos privados → URL temporal firmada)
 *
 * Se guarda solo la ruta relativa en la BD; aquí se resuelve la URL según el disco.
 */
class RefundStorage
{
    /** Disco configurado para los archivos de Requerimientos. */
    public static function disk(): string
    {
        return config('filesystems.refund_disk', 'public');
    }

    /** Sube el archivo y devuelve la ruta relativa a guardar en BD. */
    public static function put(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, self::disk());
    }

    /** URL para abrir el archivo. En discos privados (Spaces) es temporal y firmada. */
    public static function url(?string $path, int $minutes = 15): ?string
    {
        if (!$path) {
            return null;
        }
        // Si ya es una URL absoluta (datos antiguos), úsala tal cual.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = self::disk();

        // Discos locales no firman URLs: se sirven por /storage.
        if (in_array($disk, ['public', 'local'], true)) {
            return Storage::disk($disk)->url($path);
        }

        // Spaces / S3: URL temporal firmada (expira).
        try {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable $e) {
            return Storage::disk($disk)->url($path);   // respaldo
        }
    }

    /** Elimina el archivo del disco configurado. */
    public static function delete(?string $path): void
    {
        if ($path && !str_starts_with($path, 'http')) {
            Storage::disk(self::disk())->delete($path);
        }
    }
}