<?php

namespace Tests\Feature;

use App\Models\Cuti;
use App\Models\Pegawai;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;
use Mockery;
use Tests\SimpegTestCase;

class CutiPdfFilenameTest extends SimpegTestCase
{
    public function test_slashed_letter_number_is_sanitized_in_the_download_filename(): void
    {
        $owner = $this->createUserWithRole('user', ['nip' => 'CUTI-PDF-001'], ['nama' => 'Budi Test']);
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();

        $cuti = Cuti::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_cuti' => 'Cuti Tahunan',
            'status' => 'Disetujui Pimpinan',
            'no_surat_cuti' => 'W8-A1/C/XI/2026',
        ]);

        $pdf = Mockery::mock(PDF::class)->makePartial();
        $pdf->shouldReceive('download')
            ->once()
            ->with('Surat_Cuti_Budi Test_W8-A1-C-XI-2026.pdf')
            ->andReturn(new Response('pdf-content'));

        \PDF::shouldReceive('loadView')
            ->once()
            ->with('cuti.pdf', Mockery::on(fn (array $data): bool => $data['cuti']->uuid === $cuti->uuid))
            ->andReturn($pdf);

        $this->actingAs($owner)->get(route('cuti.pdf', $cuti->uuid))
            ->assertOk()
            ->assertSee('pdf-content');
    }
}
