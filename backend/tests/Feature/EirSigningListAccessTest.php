<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class EirSigningListAccessTest extends TestCase
{
    public function test_user_account_can_load_pending_signatures_without_the_eir_menu(): void
    {
        $user = new User;
        $user->usrcde = 'zzsigning';
        $user->usrlvl = 'User';

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/eir-signing?per_page=5');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }
}
