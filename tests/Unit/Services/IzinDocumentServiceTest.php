<?php

namespace Tests\Unit\Services;

use App\Services\IzinDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class IzinDocumentServiceTest extends TestCase
{
    public function test_store_document_uses_mime_extension_and_private_disk(): void
    {
        Storage::fake('local');

        $filename = (new IzinDocumentService)->storeDocument(
            UploadedFile::fake()->create('payload.exe.pdf', 100, 'application/pdf')
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', $filename);
        Storage::disk('local')->assertExists('dokumen/izin/'.$filename);
    }

    public function test_store_document_supports_jpeg_and_png_mime_types(): void
    {
        Storage::fake('local');
        $service = new IzinDocumentService;

        $jpeg = $service->storeDocument(UploadedFile::fake()->image('photo.jpg')->mimeType('image/jpeg'));
        $png = $service->storeDocument(UploadedFile::fake()->image('photo.png')->mimeType('image/png'));

        $this->assertStringEndsWith('.jpg', $jpeg);
        $this->assertStringEndsWith('.png', $png);
        Storage::disk('local')->assertExists('dokumen/izin/'.$jpeg);
        Storage::disk('local')->assertExists('dokumen/izin/'.$png);
    }

    public function test_store_document_rejects_unsupported_mime_type(): void
    {
        $this->expectException(ValidationException::class);

        (new IzinDocumentService)->storeDocument(
            UploadedFile::fake()->create('payload.txt', 10, 'text/plain')
        );
    }

    public function test_store_failure_throws_validation_exception(): void
    {
        $file = \Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getMimeType')->once()->andReturn('application/pdf');
        $file->shouldReceive('storeAs')->once()->andReturnFalse();

        $this->expectException(ValidationException::class);

        (new IzinDocumentService)->storeDocument($file);
    }

    public function test_delete_removes_private_and_legacy_public_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('dokumen/izin/document.pdf', 'private');
        Storage::disk('public')->put('dokumen/izin/document.pdf', 'legacy');

        (new IzinDocumentService)->delete('document.pdf');

        Storage::disk('local')->assertMissing('dokumen/izin/document.pdf');
        Storage::disk('public')->assertMissing('dokumen/izin/document.pdf');
    }

    public function test_delete_ignores_null_and_unsafe_filenames(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('dokumen/izin/safe.pdf', 'contents');

        $service = new IzinDocumentService;
        $service->delete(null);
        $service->delete('../safe.pdf');
        $service->delete('folder/safe.pdf');

        Storage::disk('local')->assertExists('dokumen/izin/safe.pdf');
    }

    public function test_exists_checks_private_and_legacy_public_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('dokumen/izin/private.pdf', 'private');
        Storage::disk('public')->put('dokumen/izin/legacy.pdf', 'legacy');
        $service = new IzinDocumentService;

        $this->assertTrue($service->exists('private.pdf'));
        $this->assertTrue($service->exists('legacy.pdf'));
        $this->assertFalse($service->exists('missing.pdf'));
    }

    public function test_exists_rejects_path_traversal(): void
    {
        $service = new IzinDocumentService;

        $this->assertFalse($service->exists('../secret.pdf'));
        $this->assertFalse($service->exists('folder/document.pdf'));
    }

    public function test_download_prefers_private_document_and_sets_private_cache_header(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('dokumen/izin/document.pdf', 'private');
        Storage::disk('public')->put('dokumen/izin/document.pdf', 'legacy');

        $response = (new IzinDocumentService)->download('document.pdf');

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function test_download_uses_legacy_public_document_when_private_file_is_missing(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('dokumen/izin/document.pdf', 'legacy');

        $response = (new IzinDocumentService)->download('document.pdf');

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function test_download_rejects_an_unsafe_filename(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->expectException(NotFoundHttpException::class);

        (new IzinDocumentService)->download('../secret.pdf');
    }

    public function test_download_rejects_a_missing_filename(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->expectException(NotFoundHttpException::class);

        (new IzinDocumentService)->download('missing.pdf');
    }
}
