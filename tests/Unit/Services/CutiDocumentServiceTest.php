<?php

namespace Tests\Unit\Services;

use App\Services\CutiDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CutiDocumentServiceTest extends TestCase
{
    public function test_store_document_uses_mime_extension_on_private_disk(): void
    {
        Storage::fake('local');

        $service = new CutiDocumentService;
        $file = UploadedFile::fake()->create('payload.exe.pdf', 100, 'application/pdf');

        $filename = $service->storeDocument($file);

        $this->assertStringEndsWith('.pdf', $filename);
        Storage::disk('local')->assertExists('dokumen/cuti/'.$filename);
    }

    public function test_store_failure_throws_instead_of_returning_missing_file(): void
    {
        $service = new CutiDocumentService;
        $file = \Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getMimeType')->once()->andReturn('application/pdf');
        $file->shouldReceive('storeAs')->once()->andReturnFalse();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->storeDocument($file);
    }

    public function test_exists_rejects_path_traversal(): void
    {
        $service = new CutiDocumentService;

        $this->assertFalse($service->exists('../secret.pdf'));
        $this->assertFalse($service->exists('foo/bar.pdf'));
    }
}
