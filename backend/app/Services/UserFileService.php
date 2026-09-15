<?php

namespace App\Services;

use App\Http\Requests\StoreUserFileRequest;
use App\Models\UserFile;
use App\Repositories\UserFileRepository;
use App\Support\CrudList;
use App\Support\Csv;
use App\Support\MenuFilter;
use App\Support\Text;
use App\Support\UserPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserFileService
{
    /**
     * @var list<string>
     */
    private const CSV_HEADERS = ['User ID', 'User Name', 'Password', 'User Level', 'Branch'];

    /**
     * @var list<string>
     */
    private const EXPORT_HEADERS = ['User ID', 'User Name', 'User Level'];

    /**
     * @var array<string, string>
     */
    private const CSV_ALIASES = [
        'user id' => 'usrcde',
        'usrcde' => 'usrcde',
        'user name' => 'usrname',
        'usrname' => 'usrname',
        'password' => 'usrpwd',
        'usrpwd' => 'usrpwd',
        'user level' => 'usrlvl',
        'usrlvl' => 'usrlvl',
        'branch' => 'brnchcde',
        'brnchcde' => 'brnchcde',
    ];

    /**
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'allow_add' => 'Add',
        'allow_edit' => 'Edit',
        'allow_delete' => 'Delete',
        'allow_view' => 'View',
        'allow_print' => 'Print',
        'allow_cancel' => 'Cancel',
        'allow_rates' => 'Allow Rates',
        'allow_approval' => 'Allow Approval',
    ];

    /**
     * @var list<array{chargefld: string, chargedesc: string}>
     */
    private const DEFAULT_CHARGES = [
        ['chargefld' => 'frghtamt', 'chargedesc' => 'Freight :'],
        ['chargefld' => 'vatamt', 'chargedesc' => 'Vat :'],
        ['chargefld' => 'arrorgamt', 'chargedesc' => 'Arrastre Origin :'],
        ['chargefld' => 'arrdstamt', 'chargedesc' => 'Arrastre Destination :'],
        ['chargefld' => 'ppaorgamt', 'chargedesc' => 'PPA Origin :'],
        ['chargefld' => 'ppadstamt', 'chargedesc' => 'PPA Destination :'],
        ['chargefld' => 'trckorgamt', 'chargedesc' => 'Trucking Origin :'],
        ['chargefld' => 'trckdstamt', 'chargedesc' => 'Trucking Destination :'],
        ['chargefld' => 'weiorgamt', 'chargedesc' => 'Weighing Charge Origin :'],
        ['chargefld' => 'storamt', 'chargedesc' => 'Storage :'],
        ['chargefld' => 'dummamt', 'chargedesc' => 'Demmurrages :'],
        ['chargefld' => 'othrchrgamt', 'chargedesc' => 'Other Charges :'],
    ];

    public function __construct(private UserFileRepository $users) {}

    /**
     * @return array{levels: list<array{value: string, label: string}>, branches: list<array{value: string, label: string}>}
     */
    public function lookups(): array
    {
        return [
            'levels' => $this->users->levels(),
            'branches' => $this->users->branches(),
        ];
    }

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->users->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->users->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): UserFile
    {
        return $this->users->create($this->attributesFromRequest($validated, true));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(UserFile $user, array $validated): UserFile
    {
        $this->guardEdit($user);

        return $this->users->save($user, $this->attributesFromRequest($validated, false));
    }

    public function delete(UserFile $user): void
    {
        $this->guardDelete($user);

        DB::transaction(function () use ($user) {
            $this->users->delete($user);
        });
    }

    public function resetLogin(UserFile $user): void
    {
        $this->guardReset($user);
        $this->users->resetLogin($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function access(UserFile $user): array
    {
        $this->guardAccess($user);

        return [
            'recid' => (int) $user->recid,
            'usrcde' => (string) ($user->usrcde ?? ''),
            'menus' => $this->menuAccessRows($user),
            'charges' => $this->chargeAccessRows($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function saveAccess(UserFile $user, array $validated): void
    {
        $this->guardAccess($user);

        DB::transaction(function () use ($user, $validated) {
            $this->users->replaceUserMenus(
                trim((string) ($user->usrcde ?? '')),
                $this->userMenuInserts($user, $validated['menus'] ?? []),
            );
            $this->users->replaceCharges(
                (string) $user->recid,
                $this->chargeInserts($user, $validated['charges'] ?? []),
            );
        });
    }

    public function template(): StreamedResponse
    {
        return Csv::download('user-file-template.csv', [self::CSV_HEADERS]);
    }

    public function export(string $search, string $sort = '', string $dir = 'asc'): StreamedResponse
    {
        $rows = [self::EXPORT_HEADERS];
        foreach ($this->users->getForList($search, $sort, $dir) as $user) {
            $rows[] = [
                (string) ($user->usrcde ?? ''),
                (string) ($user->usrname ?? ''),
                (string) ($user->usrlvl ?? ''),
            ];
        }

        return Csv::download('user-files.csv', $rows);
    }

    /**
     * @return array{imported?: int, errors?: list<array{line: int, code: string, message: string}>, message?: string, status?: int}
     */
    public function import(string $path): array
    {
        $csv = Csv::read($path);
        if (! $csv['ok']) {
            return ['message' => $csv['message'], 'status' => 422];
        }

        $map = Csv::headerMap($csv['header'], self::CSV_ALIASES);
        if (! isset($map['usrcde'], $map['usrname'], $map['usrpwd'])) {
            return ['message' => 'Invalid template. Required columns: User ID, User Name, Password.', 'status' => 422];
        }

        $imported = 0;
        $errors = [];
        $line = 1;

        foreach ($csv['rows'] as $row) {
            $line++;
            if (Csv::rowEmpty($row)) {
                continue;
            }

            $payload = [
                'usrcde' => Csv::cell($row, $map, 'usrcde'),
                'usrname' => Csv::cell($row, $map, 'usrname'),
                'usrpwd' => Csv::cell($row, $map, 'usrpwd'),
                'usrlvl' => Csv::cell($row, $map, 'usrlvl'),
                'brnchcde' => Csv::cell($row, $map, 'brnchcde'),
            ];

            $validator = Validator::make(
                $payload,
                (new StoreUserFileRequest)->rules(),
                (new StoreUserFileRequest)->messages()
            );
            if ($validator->fails()) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['usrcde'],
                    'message' => collect($validator->errors()->all())->implode(' '),
                ];

                continue;
            }

            try {
                $this->users->create($this->attributesFromRequest($validator->validated(), true));
                $imported++;
            } catch (Throwable) {
                $errors[] = [
                    'line' => $line,
                    'code' => $payload['usrcde'],
                    'message' => 'Unable to save this row.',
                ];
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFromRequest(array $validated, bool $creating): array
    {
        $attributes = [
            'usrname' => Text::clip($validated['usrname'] ?? '', 30),
            'usrlvl' => Text::clip($validated['usrlvl'] ?? '', 15),
            'brnchcde' => Text::clip($validated['brnchcde'] ?? '', 50),
        ];

        $password = trim((string) ($validated['usrpwd'] ?? ''));
        if ($password !== '') {
            $attributes['usrpwd'] = Text::clip($password, 30);
            if (Schema::hasColumn('userfile', 'pwd_hash')) {
                $attributes['pwd_hash'] = UserPassword::hash($password);
            }
        } elseif ($creating) {
            throw ValidationException::withMessages([
                'usrpwd' => 'This field cannot be blank',
            ]);
        }

        if ($creating) {
            $attributes['usrcde'] = Text::clip($validated['usrcde'] ?? '', 15);
            $attributes['status'] = '0';
        }

        return $attributes;
    }

    private function guardEdit(UserFile $user): void
    {
        if ($user->isProtectedAdmin()) {
            throw ValidationException::withMessages([
                'usrcde' => 'This user cannot be edited.',
            ]);
        }
    }

    private function guardDelete(UserFile $user): void
    {
        if ($user->isProtectedAdmin() || $user->isSupervisorLevel()) {
            throw ValidationException::withMessages([
                'usrcde' => 'This user cannot be deleted.',
            ]);
        }

        $actor = Auth::user();
        if ($actor && strcasecmp((string) $actor->usrcde, (string) $user->usrcde) === 0) {
            throw ValidationException::withMessages([
                'usrcde' => 'You cannot delete the currently logged-in user.',
            ]);
        }
    }

    private function guardReset(UserFile $user): void
    {
        if ($user->isProtectedAdmin()) {
            throw ValidationException::withMessages([
                'usrcde' => 'This user cannot be reset.',
            ]);
        }
    }

    private function guardAccess(UserFile $user): void
    {
        if ($user->isProtectedAdmin() || $user->isSupervisorLevel()) {
            throw ValidationException::withMessages([
                'usrcde' => 'User access cannot be changed for this user.',
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function visibleMenus()
    {
        return MenuFilter::filterFlatMenus($this->users->menus());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function menuAccessRows(UserFile $user): array
    {
        $usrcde = trim((string) ($user->usrcde ?? ''));
        $assigned = $this->users->userMenus($usrcde);
        $out = [];

        foreach ($this->visibleMenus() as $menu) {
            $match = $assigned->first(function ($row) use ($menu) {
                $sameIndex = (string) ($row->menidx ?? '') === (string) ($menu->menidx ?? '');
                $sameCaption = (string) ($row->mencap ?? '') === (string) ($menu->mencap ?? '');
                $sameProgram = (string) ($row->menprg ?? '') === (string) ($menu->menprg ?? '');

                return $sameIndex && ($sameCaption || $sameProgram);
            });

            $permissions = [];
            $level = (int) ($menu->menlvl ?? 0);
            if ($level !== 1) {
                foreach (self::PERMISSIONS as $field => $caption) {
                    if ((string) ($menu->{$field} ?? '') !== '1') {
                        continue;
                    }
                    $permissions[] = [
                        'field' => $field,
                        'caption' => $caption,
                        'checked' => $match !== null && (string) ($match->{$field} ?? '') === '1',
                    ];
                }
            }

            $out[] = [
                'menidx' => (string) ($menu->menidx ?? ''),
                'mencap' => (string) ($menu->mencap ?? ''),
                'menprg' => (string) ($menu->menprg ?? ''),
                'modcde' => (string) ($menu->modcde ?? ''),
                'linenum' => $menu->linenum ?? null,
                'mengrp' => (string) ($menu->mengrp ?? ''),
                'mensub' => (string) ($menu->mensub ?? ''),
                'menvis' => $menu->menvis ?? 0,
                'mennum' => (string) ($menu->mennum ?? ''),
                'menlvl' => $level,
                'aclkey' => (string) ($menu->aclkey ?? ''),
                'checked' => $match !== null,
                'permissions' => $permissions,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{chargefld: string, chargedesc: string, label: string, editable: bool}>
     */
    private function chargeAccessRows(UserFile $user): array
    {
        $existing = $this->users->charges((string) $user->recid);
        $out = [];

        foreach (self::DEFAULT_CHARGES as $index => $charge) {
            $row = $existing->values()->get($index);
            $editable = false;
            if ($row) {
                $editable = trim((string) ($row->is_editable ?? '')) !== 'readonly';
            }

            $out[] = [
                'chargefld' => $charge['chargefld'],
                'chargedesc' => $charge['chargedesc'],
                'label' => rtrim($charge['chargedesc'], ' :'),
                'editable' => $editable,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $posted
     * @return list<array<string, mixed>>
     */
    private function userMenuInserts(UserFile $user, array $posted): array
    {
        $menus = $this->visibleMenus()->values();
        if (count($posted) !== $menus->count()) {
            throw ValidationException::withMessages([
                'menus' => 'User access menu list is out of date. Reload and try again.',
            ]);
        }

        $usrcde = Text::clip($user->usrcde ?? '', 15);
        $rows = [];

        foreach ($menus as $index => $menu) {
            $item = $posted[$index] ?? [];
            if (! ($item['checked'] ?? false)) {
                continue;
            }

            $row = [
                'usrcde' => $usrcde,
                'modcde' => Text::clip($menu->modcde ?? '', 20),
                'menprg' => Text::clip($menu->menprg ?? '', 50),
                'mencap' => Text::clip($menu->mencap ?? '', 100),
                'linenum' => $menu->linenum ?? null,
                'mengrp' => Text::clip($menu->mengrp ?? '', 50),
                'mensub' => Text::clip($menu->mensub ?? '', 50),
                'menvis' => $menu->menvis ?? 0,
                'menidx' => Text::clip($menu->menidx ?? '', 15),
                'mennum' => Text::clip($menu->mennum ?? '', 15),
                'menlvl' => Text::clip($menu->menlvl ?? '', 25),
                'aclkey' => Text::clip($menu->aclkey ?? '', 25),
            ];

            $permissions = is_array($item['permissions'] ?? null) ? $item['permissions'] : [];
            foreach (array_keys(self::PERMISSIONS) as $field) {
                if ((string) ($menu->{$field} ?? '') !== '1') {
                    continue;
                }
                $row[$field] = ! empty($permissions[$field]) ? 1 : 0;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $posted
     * @return list<array<string, mixed>>
     */
    private function chargeInserts(UserFile $user, array $posted): array
    {
        $rows = [];
        foreach (self::DEFAULT_CHARGES as $index => $charge) {
            $item = $posted[$index] ?? [];
            $editable = (bool) ($item['editable'] ?? false);
            $field = $charge['chargefld'];

            $rows[] = [
                'usercde' => Text::clip((string) $user->recid, 15),
                'chargefld' => Text::clip($field, 30),
                'chargedesc' => Text::clip($charge['chargedesc'], 50),
                'idnam' => Text::clip('txt'.$field, 30),
                'txtclass' => Text::clip('numeric charges', 100),
                'is_show' => 1,
                'sortorder' => $index + 1,
                'is_editable' => $editable ? '' : 'readonly',
                'textstyle' => $editable
                    ? 'border:1px black solid;text-align:right;width:100%;'
                    : 'border:1px black solid;background:gainsboro;text-align:right;width:100%;',
            ];
        }

        return $rows;
    }
}
