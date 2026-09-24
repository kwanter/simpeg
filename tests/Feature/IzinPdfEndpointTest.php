<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Support\IzinType;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;
use Mockery;
use Tests\SimpegTestCase;

class IzinPdfEndpointTest extends SimpegTestCase
{
    private function expectPdfDownload(Izin $izin, string $template, string $filename): void
    {
        $pdf = Mockery::mock(PDF::class)->makePartial();
        $pdf->shouldReceive('download')->once()->with($filename)->andReturn(new Response('pdf-content'));

        \PDF::shouldReceive('loadView')
            ->once()
            ->with($template, Mockery::on(fn (array $data): bool => $data['izin']->uuid === $izin->uuid))
            ->andReturn($pdf);
    }

    public function test_approved_owner_downloads_pdf_using_type_template_and_letter_number_filename(): void
    {
        $owner = $this->createUserWithRole('user', ['nip' => 'PDF-OWNER-001'], ['nama' => 'Ayu Test']);
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::KELUAR_KANTOR,
            'status' => 'Disetujui',
            'verifikasi_atasan' => 'Disetujui',
            'no_surat_izin' => 'IZIN-2026-001',
        ]);
        $pdf = Mockery::mock(PDF::class)->makePartial();
        $pdf->shouldReceive('download')
            ->once()
            ->with('Surat_Izin_Ayu Test_IZIN-2026-001.pdf')
            ->andReturn(new Response('pdf-content'));

        \PDF::shouldReceive('loadView')
            ->once()
            ->with('izin.pdf-keluar-kantor', Mockery::on(fn (array $data): bool => $data['izin']->uuid === $izin->uuid))
            ->andReturn($pdf);

        $response = $this->actingAs($owner)->get(route('izin.pdf', $izin->uuid));

        $response->assertOk()->assertSee('pdf-content');
    }

    public function test_pulang_cepat_uses_the_shared_single_level_pdf_template(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::PULANG_CEPAT,
            'status' => 'Disetujui',
            'no_surat_izin' => 'IZIN-2026-004',
        ]);
        $this->expectPdfDownload($izin, 'izin.pdf-keluar-kantor', 'Surat_Izin_'.$pegawai->nama.'_IZIN-2026-004.pdf');

        $this->actingAs($owner)->get(route('izin.pdf', $izin->uuid))->assertOk();
    }

    public function test_tidak_masuk_uses_its_dedicated_pdf_template(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::TIDAK_MASUK,
            'status' => 'Disetujui',
            'no_surat_izin' => 'IZIN-2026-005',
        ]);
        $this->expectPdfDownload($izin, 'izin.pdf-tidak-masuk', 'Surat_Izin_'.$pegawai->nama.'_IZIN-2026-005.pdf');

        $this->actingAs($owner)->get(route('izin.pdf', $izin->uuid))->assertOk();
    }

    public function test_pdf_download_is_forbidden_when_letter_number_is_missing(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'status' => 'Disetujui Atasan',
            'verifikasi_atasan' => 'Disetujui',
            'no_surat_izin' => null,
        ]);

        $this->actingAs($owner)->get(route('izin.pdf', $izin->uuid))->assertForbidden();
    }

    public function test_pdf_generation_is_forbidden_without_approved_status_and_letter_number(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'status' => 'Diajukan',
            'no_surat_izin' => 'IZIN-2026-003',
        ]);

        $this->actingAs($owner)->get(route('izin.pdf', $izin->uuid))->assertForbidden();
    }
}
