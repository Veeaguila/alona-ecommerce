<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RegistrationDocumentStorage
{
    public function store(UploadedFile $file, string $folder): string
    {
        $fileName = Str::uuid() . '.' . $file->extension();
        $path = $file->storePubliclyAs($folder, $fileName, 'public');

        if ($path === false) {
            throw new RuntimeException('The document could not be stored.');
        }

        return Storage::disk('public')->url($path);
    }
}
