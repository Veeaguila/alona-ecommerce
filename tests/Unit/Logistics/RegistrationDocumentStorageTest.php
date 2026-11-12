<?php

namespace Tests\Unit\Logistics;

use App\Services\RegistrationDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationDocumentStorageTest extends TestCase
{
    public function test_registration_documents_are_saved_to_private_local_storage(): void
    {
        Storage::fake('local');

        $url = app(RegistrationDocumentStorage::class)->store(
            UploadedFile::fake()->image('license.png'),
            'license-documents',
        );

        $files = Storage::disk('local')->allFiles('license-documents');

        $this->assertCount(1, $files);
        $this->assertSame($files[0], $url);
        $this->assertStringEndsWith('.png', $files[0]);
    }
}
