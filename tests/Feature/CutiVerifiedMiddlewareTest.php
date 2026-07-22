<?php

namespace Tests\Feature;

use Tests\SimpegTestCase;

class CutiVerifiedMiddlewareTest extends SimpegTestCase
{
    public function test_unverified_user_cannot_access_cuti(): void
    {
        $user = $this->createUserWithRole('user', ['email_verified_at' => null]);
        $user->givePermissionTo('create cuti');

        $this->actingAs($user)->get(route('cuti.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_cuti(): void
    {
        $user = $this->createUserWithRole('user');

        $this->actingAs($user)->get(route('cuti.index'))->assertOk();
    }
}
