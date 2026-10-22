<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièces jointes (factures fournisseurs, preuves de paiement…) : stockage privé
 * (storage/app/private), servies uniquement par des routes authentifiées.
 */
final class PrivateFile
{
    /** Validation commune : image ou PDF, 5 Mo maximum. */
    public const RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    public static function store(?UploadedFile $file, string $directory): ?string
    {
        return $file?->store($directory, 'local');
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    public static function response(?string $path): StreamedResponse
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
