<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use App\Repositories\ExpenseRepository;
use App\Support\CrudList;
use App\Support\MenuPermission;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    private const MENPRG = 'view_expenses.php';

    public function __construct(private ExpenseRepository $expenses) {}

    /**
     * @return array{
     *     shiftcde: string,
     *     permissions: array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool}
     * }
     */
    public function lookups(User $user): array
    {
        $shift = $this->expenses->branchShift(trim((string) ($user->brnchcde ?? '')));

        return [
            'shiftcde' => trim((string) ($shift->shiftcde ?? '')),
            'permissions' => $this->permissionsFor($user),
        ];
    }

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->expenses->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->expenses->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $user): Expense
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_ADD);

        return DB::transaction(function () use ($validated, $user) {
            $docnum = Text::clip($this->expenses->nextDocnum(), 15);
            if ($docnum === '') {
                throw ValidationException::withMessages([
                    'docnum' => ['Missing Doc. No.'],
                ]);
            }

            return $this->expenses->create($this->attributesFromRequest($validated, $user, $docnum, null));
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Expense $expense, array $validated, User $user): Expense
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        $docnum = trim((string) ($expense->docnum ?? ''));

        return $this->expenses->save(
            $expense,
            $this->attributesFromRequest($validated, $user, $docnum, $expense->trndte),
        );
    }

    public function delete(Expense $expense, User $user): void
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_DELETE);

        $this->expenses->delete($expense);
    }

    /**
     * @return array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool}
     */
    private function permissionsFor(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                'can_add' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_view' => true,
            ];
        }

        $perms = $this->expenses->menuPermissions((string) $user->usrcde);
        if ($perms === null) {
            return [
                'can_add' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_view' => true,
            ];
        }

        return [
            'can_add' => $perms['allow_add'],
            'can_edit' => $perms['allow_edit'],
            'can_delete' => $perms['allow_delete'],
            'can_view' => $perms['allow_view'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFromRequest(array $validated, User $user, string $docnum, mixed $existingTrndte): array
    {
        $branch = trim((string) ($user->brnchcde ?? ''));
        $shift = $this->expenses->branchShift($branch);
        $now = now('Asia/Manila');
        $date = $this->datePart($existingTrndte) ?: $now->format('Y-m-d');

        return [
            'docnum' => $docnum,
            'itmdsc' => Text::clip($validated['itmdsc'] ?? '', 100),
            'untprc' => round((float) ($validated['untprc'] ?? 0), 2),
            'trndte' => $date.' '.$now->format('H:i:s'),
            'usrnam' => Text::clip($user->usrname ?? $user->usrcde ?? '', 20),
            'usrcde' => Text::clip($user->usrcde ?? '', 20),
            'shiftcde' => Text::clip($shift?->shiftcde ?? '', 20),
            'shiftdte' => $this->datePart($shift?->shiftdate ?? null),
            'brnchcde' => Text::clip($shift?->branchcde ?? $branch, 100),
        ];
    }

    private function datePart(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 10);
    }
}
