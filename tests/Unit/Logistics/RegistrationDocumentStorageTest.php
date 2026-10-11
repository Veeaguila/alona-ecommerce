<?php

namespace Tests\Unit\Logistics;

use App\Services\RegistrationDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationDocumentStorageTest extends TestCase
{
    public function test_registration_documents_are_saved_to_public_local_storage(): void
    {
        Storage::fake('public');

        $url = app(RegistrationDocumentStorage::class)->store(
            UploadedFile::fake()->image('license.png'),
            'license-documents',
        );

        $files = Storage::disk('public')->allFiles('license-documents');

        $this->assertCount(1, $files);
        $this->assertSame(Storage::disk('public')->url($files[0]), $url);
        $this->assertStringEndsWith('.png', $files[0]);
    }
}
