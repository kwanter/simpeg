<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivatizeHrDocumentsCommandTest extends TestCase
{
    public function test_moves_legacy_public_hr_documents_to_private_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('dokumen/cuti/cuti.pdf', 'cuti');
        Storage::disk('public')->put('dokumen/izin/izin.pdf', 'izin');

        $this->artisan('documents:privatize')->assertSuccessful();

        Storage::disk('local')->assertExists('dokumen/cuti/cuti.pdf');
        Storage::disk('local')->assertExists('dokumen/izin/izin.pdf');
        Storage::disk('public')->assertMissing('dokumen/cuti/cuti.pdf');
        Storage::disk('public')->assertMissing('dokumen/izin/izin.pdf');
    }
}
