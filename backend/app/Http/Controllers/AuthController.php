<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LanAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'app_title' => config('app.name'),
            'ip' => LanAccess::clientIp($request),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'usrname' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $usrname = trim($credentials['usrname']);
        $password = trim($credentials['password']);

        $user = User::query()->where('usrname', $usrname)->first();

        if (! $user || ! $user->passwordMatches($password)) {
            return response()->json([
                'code' => 'invalid_login',
                'message' => 'Incorrect password or username',
            ], 422);
        }

        $user->upgradePasswordHash($password);

        Auth::login($user);
        $request->session()->regenerate();

        $company = $this->companyRow();
        $request->session()->put('usrname', trim((string) $user->usrname));
        $request->session()->put('usrlvl', trim((string) $user->usrlvl));
        $request->session()->put('comcde', $company['comcde']);
        $request->session()->put('comdsc', $company['comdsc']);

        return response()->json($this->authPayload($user));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->authPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(User $user): array
    {
        $company = $this->companyRow();

        return array_merge($user->toAuthArray(), [
            'comcde' => $company['comcde'],
            'comdsc' => $company['comdsc'],
        ]);
    }

    /**
     * @return array{comcde: string, comdsc: string}
     */
    private function companyRow(): array
    {
        $row = DB::table('companyfile')->first();

        $comdsc = '';
        if ($row && isset($row->comdsc) && trim((string) $row->comdsc) !== '') {
            $comdsc = (string) $row->comdsc;
        } elseif ($row && isset($row->companydescription)) {
            $comdsc = (string) $row->companydescription;
        }

        return [
            'comcde' => $row->comcde ?? '',
            'comdsc' => $comdsc,
        ];
    }
}
