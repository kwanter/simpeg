<?php

namespace Tests\Feature;

use Tests\SimpegTestCase;

class NavigationVisibilityTest extends SimpegTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_admin_sees_pegawai_cuti_izin_and_hari_libur_links(): void
    {
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo(['view izin', 'view hari libur']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pegawai', false);
        $response->assertSee('Cuti', false);
        $response->assertSee('Pengajuan Izin', false);
        $response->assertSee('Izin Keluar Kantor', false);
        $response->assertSee('Izin Tidak Masuk', false);
        $response->assertSee('Hari Libur', false);
    }

    public function test_regular_user_does_not_see_pegawai_cuti_or_admin_menus(): void
    {
        $user = $this->createUserWithRole('user');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        // Nav links carry an href; assert the link targets are absent
        // rather than bare words ("Pegawai" appears in the page title).
        $response->assertDontSee('href="'.url('/users').'"', false);
        $response->assertDontSee('href="'.url('/pegawai').'"', false);
        $response->assertDontSee('Hari Libur', false);
    }

    public function test_atasan_pimpinan_sees_pegawai_cuti_izin_but_not_users(): void
    {
        $user = $this->createUserWithRole('atasan-pimpinan');
        $user->givePermissionTo('view izin');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pegawai', false);
        $response->assertSee('Cuti', false);
        $response->assertSee('Pengajuan Izin', false);
        $response->assertSee('Izin Keluar Kantor', false);
        $response->assertSee('Izin Tidak Masuk', false);
        $response->assertDontSee('Users', false);
    }

    public function test_pimpinan_sees_pegawai_cuti_izin(): void
    {
        $user = $this->createUserWithRole('pimpinan');
        $user->givePermissionTo('view izin');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pegawai', false);
        $response->assertSee('Cuti', false);
        $response->assertSee('Pengajuan Izin', false);
    }

    public function test_verifikator_sees_pegawai_cuti(): void
    {
        $user = $this->createUserWithRole('verifikator');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Pegawai', false);
        $response->assertSee('Cuti', false);
    }

    public function test_user_without_view_izin_permission_does_not_see_izin_menus(): void
    {
        $user = $this->createUserWithRole('user');
        $user->revokePermissionTo('view izin');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Pengajuan Izin', false);
        $response->assertDontSee('Izin Keluar Kantor', false);
        $response->assertDontSee('Izin Tidak Masuk', false);
        $response->assertDontSee('Izin PERMA No. 7/2016', false);
    }

    public function test_user_without_view_hari_libur_permission_does_not_see_hari_libur(): void
    {
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo(['view izin', 'view hari libur']);
        $admin->revokePermissionTo('view hari libur');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Hari Libur', false);
    }

    public function test_mobile_nav_renders_same_items_as_desktop(): void
    {
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo(['view izin', 'view hari libur']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        // Both sm:flex (desktop) and sm:hidden (mobile) sections must contain the
        // PERMA izin labels, proving desktop/mobile parity for this role.
        $content = $response->getContent();
        $desktopSection = $this->extract_section($content, 'hidden space-x-8 sm:-my-px sm:ms-10 sm:flex', 'sm:hidden');
        $mobileSection = $this->extract_section($content, 'sm:hidden', '</nav>');

        $permaLabels = ['Pengajuan Izin', 'Izin Keluar Kantor', 'Izin Tidak Masuk'];
        foreach ($permaLabels as $label) {
            $this->assertStringContainsString(
                $label,
                $desktopSection,
                "Desktop nav missing {$label}"
            );
            $this->assertStringContainsString(
                $label,
                $mobileSection,
                "Mobile nav missing {$label}"
            );
        }
    }

    private function extract_section(string $haystack, string $from, string $to): string
    {
        $start = strpos($haystack, $from);
        $end = $start === false ? false : strpos($haystack, $to, $start);
        if ($start === false || $end === false) {
            return '';
        }

        return substr($haystack, $start, $end - $start);
    }
}
