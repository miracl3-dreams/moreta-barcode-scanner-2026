<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserLevelTest extends TestCase
{
    public function test_supervisor_level_ignores_case_and_surrounding_spaces(): void
    {
        $user = new User;
        $user->usrlvl = ' Supervisor ';

        $this->assertTrue($user->isAdmin());
    }

    public function test_user_level_is_not_supervisor(): void
    {
        $user = new User;
        $user->usrlvl = 'User';

        $this->assertFalse($user->isAdmin());
    }
}
