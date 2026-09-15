<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\SetPasswordRepository;
use App\Support\Text;
use Illuminate\Validation\ValidationException;

class SetPasswordService
{
    public function __construct(private SetPasswordRepository $passwords) {}

    /**
     * @param  array{oldpwd: string, newpwd: string, newpwd_confirmation: string}  $validated
     */
    public function update(User $user, array $validated): void
    {
        $old = (string) ($validated['oldpwd'] ?? '');
        $new = (string) ($validated['newpwd'] ?? '');
        $confirm = (string) ($validated['newpwd_confirmation'] ?? '');

        if (! $user->passwordMatches($old)) {
            throw ValidationException::withMessages([
                'oldpwd' => 'Invalid Password!',
            ]);
        }

        if ($new !== $confirm) {
            throw ValidationException::withMessages([
                'newpwd' => 'Reenter Passwords',
                'newpwd_confirmation' => 'Reenter Passwords',
            ]);
        }

        $this->passwords->updatePassword(
            trim((string) ($user->usrcde ?? '')),
            Text::clip($new, 30),
        );
    }
}
