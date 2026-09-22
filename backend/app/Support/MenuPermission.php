<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MenuPermission
{
    public const ACTION_VIEW = 'view';

    public const ACTION_ADD = 'add';

    public const ACTION_EDIT = 'edit';

    public const ACTION_DELETE = 'delete';

    public const ACTION_PRINT = 'print';

    public const ACTION_RATES = 'rates';

    public const ACTION_APPROVAL = 'approval';

    public const ACTION_CANCEL = 'cancel';

    /**
     * @var array<string, string>
     */
    private const ACTION_COLUMNS = [
        self::ACTION_VIEW => 'allow_view',
        self::ACTION_ADD => 'allow_add',
        self::ACTION_EDIT => 'allow_edit',
        self::ACTION_DELETE => 'allow_delete',
        self::ACTION_PRINT => 'allow_print',
        self::ACTION_RATES => 'allow_rates',
        self::ACTION_APPROVAL => 'allow_approval',
        self::ACTION_CANCEL => 'allow_cancel',
    ];

    /**
     * @var list<string>
     */
    private const BASE_COLUMNS = [
        'allow_add',
        'allow_edit',
        'allow_delete',
        'allow_view',
        'allow_print',
    ];

    /**
     * @return array<string, bool>|null
     */
    public static function forUser(User $user, string $menprg): ?array
    {
        if ($user->isAdmin()) {
            return self::allAllowed();
        }

        return self::forUserCode((string) $user->usrcde, $menprg);
    }

    /**
     * @return array<string, bool>|null
     */
    public static function forUserCode(string $usrcde, string $menprg): ?array
    {
        if ($usrcde === '' || ! Schema::hasTable('user_menus')) {
            return null;
        }

        $columns = self::columnsForTable();
        $row = DB::table('user_menus')
            ->where('usrcde', $usrcde)
            ->where('menprg', $menprg)
            ->first($columns);

        if ($row === null) {
            return null;
        }

        $permissions = [];
        foreach ($columns as $column) {
            $permissions[$column] = (int) ($row->{$column} ?? 0) === 1;
        }

        return $permissions;
    }

    public static function allows(User $user, string $menprg, string $action): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $column = self::ACTION_COLUMNS[$action] ?? null;
        if ($column === null) {
            return false;
        }

        $permissions = self::forUser($user, $menprg);
        if ($permissions === null) {
            return false;
        }

        // Same as Moreta: an assigned EIR program can open the pending-signature
        // list even when the View checkbox is off.
        if ($action === self::ACTION_VIEW || $action === self::ACTION_PRINT) {
            return true;
        }

        return (bool) ($permissions[$column] ?? false);
    }

    public static function assert(User $user, string $menprg, string $action): void
    {
        if (self::allows($user, $menprg, $action)) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to perform this action.');
    }

    public static function actionFromRequest(Request $request): string
    {
        $path = strtolower($request->path());

        if (str_contains($path, '/approve')) {
            return self::ACTION_APPROVAL;
        }

        if (str_contains($path, '/cancel')) {
            return self::ACTION_CANCEL;
        }

        if (
            str_contains($path, '/tag')
            || str_contains($path, '/accept')
            || str_contains($path, '/reset-login')
            || str_contains($path, '/return')
            || str_contains($path, '/payments')
            || str_contains($path, '/apply')
            || str_contains($path, '/bolnum')
        ) {
            return self::ACTION_EDIT;
        }

        if (str_contains($path, '/pdf') || str_contains($path, '/export')) {
            return self::ACTION_PRINT;
        }

        if (str_contains($path, '/import')) {
            return self::ACTION_ADD;
        }

        if (str_contains($path, '/rates')) {
            return self::ACTION_RATES;
        }

        return match (strtoupper($request->method())) {
            'POST' => self::ACTION_ADD,
            'PUT', 'PATCH' => self::ACTION_EDIT,
            'DELETE' => self::ACTION_DELETE,
            default => self::ACTION_VIEW,
        };
    }

    /**
     * @return array<string, bool>
     */
    private static function allAllowed(): array
    {
        $permissions = [];
        foreach (self::columnsForTable() as $column) {
            $permissions[$column] = true;
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    private static function columnsForTable(): array
    {
        $columns = self::BASE_COLUMNS;
        foreach (['allow_rates', 'allow_approval', 'allow_cancel'] as $optional) {
            if (Schema::hasColumn('user_menus', $optional)) {
                $columns[] = $optional;
            }
        }

        return $columns;
    }
}
