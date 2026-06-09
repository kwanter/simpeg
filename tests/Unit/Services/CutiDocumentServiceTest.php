<?php

namespace Tests\Unit\Services;

use App\Services\CutiDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SimpegTestCase;

class CutiDocumentServiceTest extends SimpegTestCase
{
    use RefreshDatabase;

    private CutiDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = new CutiDocumentService;
    }

    public function test_store_document_saves_file(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $filename = $this->service->storeDocument($file);

        $this->assertNotNull($filename);
        $this->assertTrue(str_ends_with($filename, '.pdf'));
        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists('public/dokumen/cuti/'.$filename), 'File should be stored at public/dokumen/cuti/'.$filename);
    }

    public function test_store_document_preserves_extension(): void
    {
        $file = UploadedFile::fake()->create('scan.jpg', 50, 'image/jpeg');
        $filename = $this->service->storeDocument($file);

        $this->assertTrue(str_ends_with($filename, '.jpg'));
    }

    public function test_store_document_generates_unique_names(): void
    {
        $file1 = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $file2 = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $name1 = $this->service->storeDocument($file1);
        $name2 = $this->service->storeDocument($file2);

        $this->assertNotSame($name1, $name2);
    }
}
