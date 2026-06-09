<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CutiDocumentService
{
    /**
     * Store an uploaded document and return the stored filename.
     *
     * @return string The stored filename (UUID-based)
     */
    public function storeDocument(UploadedFile $file): string
    {
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $file->storeAs('public/dokumen/cuti', $filename);

        return $filename;
    }
}
