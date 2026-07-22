<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CutiDocumentService
{
    private const DISK = 'local';

    private const DIR = 'dokumen/cuti';

    private const MIME_EXT = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Store an uploaded document and return the stored filename.
     */
    public function storeDocument(UploadedFile $file): string
    {
        $ext = self::MIME_EXT[$file->getMimeType()] ?? null;
        if (! $ext) {
            throw ValidationException::withMessages(['dokumen' => ['Tipe dokumen tidak didukung.']]);
        }

        $filename = Str::uuid()->toString().'.'.$ext;
        if ($file->storeAs(self::DIR, $filename, self::DISK) === false) {
            throw ValidationException::withMessages(['dokumen' => ['Dokumen gagal disimpan.']]);
        }

        return $filename;
    }

    public function delete(?string $filename): void
    {
        if (! $filename || str_contains($filename, '..') || str_contains($filename, '/')) {
            return;
        }

        Storage::disk(self::DISK)->delete(self::DIR.'/'.$filename);
        // Legacy public-disk files from before private storage migration
        Storage::disk('public')->delete('dokumen/cuti/'.$filename);
    }

    public function exists(string $filename): bool
    {
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            return false;
        }

        return Storage::disk(self::DISK)->exists(self::DIR.'/'.$filename)
            || Storage::disk('public')->exists('dokumen/cuti/'.$filename);
    }

    public function download(string $filename): StreamedResponse
    {
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            abort(404);
        }

        $path = self::DIR.'/'.$filename;
        if (Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->download($path, null, ['Cache-Control' => 'private, no-store']);
        }

        $legacy = 'dokumen/cuti/'.$filename;
        if (Storage::disk('public')->exists($legacy)) {
            return Storage::disk('public')->download($legacy, null, ['Cache-Control' => 'private, no-store']);
        }

        abort(404);
    }
}
