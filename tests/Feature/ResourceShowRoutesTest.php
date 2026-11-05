<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\SimpegTestCase;

class ResourceShowRoutesTest extends SimpegTestCase
{
    public function test_show_routes_are_not_registered_for_controllers_without_a_show_action(): void
    {
        $this->assertFalse(Route::has('users.show'));
        $this->assertFalse(Route::has('roles.show'));
        $this->assertFalse(Route::has('permissions.show'));
    }

    /**
     * The URL still serves PUT/PATCH/DELETE, so a GET is a missing method
     * (405) rather than a missing route — the important part is that it no
     * longer reaches a controller action that does not exist (500).
     */
    public function test_show_urls_return_method_not_allowed_instead_of_a_server_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        $target = $this->createUserWithRole('user');
        $missing = fake()->uuid();

        $this->actingAs($admin)->get('/users/'.$target->uuid)->assertStatus(405);
        $this->actingAs($admin)->get('/roles/'.$missing)->assertStatus(405);
        $this->actingAs($admin)->get('/permissions/'.$missing)->assertStatus(405);
    }
}
