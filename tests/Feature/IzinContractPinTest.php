<?php

namespace Tests\Feature;

use App\Http\Requests\UpdateIzinRequest;
use App\Models\Izin;
use App\Models\Pegawai;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\SimpegTestCase;

class IzinContractPinTest extends SimpegTestCase
{
    private function createExistingIzin(): Izin
    {
        return Izin::factory()->create([
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);
    }

    private function storePayload(Pegawai $atasan, Pegawai $pimpinan): array
    {
        return [
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Keperluan pribadi',
        ];
    }

    public function test_unknown_izin_uuid_yields_not_found_for_any_authenticated_role(): void
    {
        $user = $this->createUserWithRole('user');
        $admin = $this->createUserWithRole('admin');
        $unknownUuid = (string) Str::uuid();

        $this->actingAs($user)
            ->put(route('izin.update', $unknownUuid), ['no_surat_izin' => 'X'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('izin.update', $unknownUuid), ['no_surat_izin' => 'X'])
            ->assertNotFound();
    }

    public function test_existing_izin_owned_by_someone_else_is_forbidden(): void
    {
        $other = $this->createUserWithRole('user');
        $izin = $this->createExistingIzin();

        $this->actingAs($other)
            ->put(route('izin.update', $izin->uuid), ['no_surat_izin' => 'X'])
            ->assertForbidden();
    }

    public function test_resolved_izin_or_fail_aborts_not_found_for_an_unknown_uuid(): void
    {
        $request = UpdateIzinRequest::create(route('izin.update', (string) Str::uuid()), 'PUT', [
            '_token' => 'test-token',
            '_method' => 'PUT',
            'no_surat_izin' => 'IZIN-2026-001',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $this->expectException(NotFoundHttpException::class);

        $request->resolvedIzinOrFail();
    }

    public function test_guest_cannot_reach_the_izin_write_routes(): void
    {
        $izin = $this->createExistingIzin();

        $this->post(route('izin.store'), [])->assertRedirect(route('login'));
        $this->put(route('izin.update', $izin->uuid), [])->assertRedirect(route('login'));
    }

    public function test_store_persists_the_explicitly_assigned_uuid(): void
    {
        $user = $this->createUserWithRole('user');
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();

        $this->actingAs($user)
            ->post(route('izin.store'), $this->storePayload($atasan, $pimpinan))
            ->assertRedirect(route('izin.index'));

        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $izin = Izin::where('pegawai_uuid', $pegawai->uuid)->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $izin->uuid
        );

        // An ordered UUID (what the HasUuids trait would generate) encodes a
        // millisecond timestamp in its first 12 hex characters, so its prefix
        // tracks now() to within a few milliseconds. A random v4 prefix is
        // uniform over 2^48, so a +/-1e6 ms window yields a false positive with
        // probability ~7e-9 - three orders of magnitude rarer than the 1e9 ms
        // (11 day) window that was here before. A value-only test cannot be made
        // fully deterministic: Str::orderedUuid() reads the same $uuidFactory
        // hook as Str::uuid() (vendor/laravel/framework .../Support/Str.php),
        // so injecting a factory cannot tell the two generators apart.
        $prefix = (int) hexdec(substr($izin->uuid, 0, 12));
        $nowInMilliseconds = (int) round(microtime(true) * 1000);
        $this->assertGreaterThan(
            1_000_000,
            abs($prefix - $nowInMilliseconds),
            'The persisted uuid looks like a time-prefixed ordered UUID; the controller must keep assigning Str::uuid().'
        );
    }
}
