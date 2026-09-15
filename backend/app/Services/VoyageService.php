<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voyage;
use App\Repositories\BillOfLadingRepository;
use App\Repositories\VoyageRepository;
use App\Support\CrudList;
use App\Support\MenuPermission;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageService
{
    private const MENPRG = 'view_bol.php';

    public function __construct(
        private VoyageRepository $voyages,
        private BillOfLadingRepository $bills,
    ) {}

    /**
     * @return array{
     *     vessels: list<array{vsslcde: string, vssldsc: string}>,
     *     destinations: list<array{dstcde: string, dstdsc: string}>,
     *     permissions: array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool}
     * }
     */
    public function lookups(User $user): array
    {
        return [
            'vessels' => $this->voyages->vesselOptions(),
            'destinations' => $this->voyages->destinationOptions(),
            'permissions' => $this->permissionsFor($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{voyage: array<string, mixed>, bills: list<array<string, mixed>>}
     */
    public function updateSailingDate(Voyage $voyage, array $validated, User $user): array
    {
        if (strtoupper(trim((string) ($user->usrlvl ?? ''))) === 'USER') {
            throw ValidationException::withMessages([
                'trndte' => ['Sailing date cannot be edited.'],
            ]);
        }

        $voynum = trim((string) ($voyage->voynum ?? ''));
        $this->voyages->updateSailingDates($voynum, [
            'trndte' => $this->dateOrNull($validated['trndte'] ?? null),
            'etadte' => $this->dateOrNull($validated['etadte'] ?? null),
        ]);

        $fresh = Voyage::query()->api()->where('recid', $voyage->recid)->firstOrFail();

        return $this->show($fresh);
    }

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->voyages->getForList($search));
        }

        return CrudList::fromPaginator($this->voyages->paginateForList($search, $pageSize));
    }

    /**
     * @return array{voyage: array<string, mixed>, bills: list<array<string, mixed>>}
     */
    public function show(Voyage $voyage): array
    {
        $voynum = trim((string) ($voyage->voynum ?? ''));

        return [
            'voyage' => $voyage->toListArray(),
            'bills' => $this->bills->listByVoynum($voynum)->map(fn ($row) => $row->toListArray())->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{voyage: array<string, mixed>, bills: list<array<string, mixed>>}
     */
    public function create(array $validated, User $user): array
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_ADD);

        $attributes = $this->attributesFromRequest($validated);
        $duplicate = (bool) ($validated['duplicate'] ?? false);

        $voyage = DB::transaction(function () use ($attributes, $duplicate) {
            $created = $this->voyages->create($attributes);

            if ($duplicate) {
                $dupAttributes = $attributes;
                $dupAttributes['voynum'] = $this->duplicateVoyageId($attributes['voynum']);
                $dupAttributes['voytype'] = 'Cons';

                if ($this->voyages->findByVoynum($dupAttributes['voynum']) !== null) {
                    throw ValidationException::withMessages([
                        'voynum' => ['Duplicate Voyage Number.'],
                    ]);
                }

                $this->voyages->create($dupAttributes);
            }

            return $created->fresh() ?? $created;
        });

        $voyage = Voyage::query()->api()->where('recid', $voyage->recid)->firstOrFail();

        return $this->show($voyage);
    }

    public function delete(Voyage $voyage, User $user): void
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_DELETE);

        $voynum = trim((string) ($voyage->voynum ?? ''));
        if ($voynum === '') {
            $this->voyages->delete($voyage);

            return;
        }

        DB::transaction(function () use ($voynum) {
            $this->voyages->unlockSealsForVoyage($voynum);
            $this->voyages->deleteBillsByVoynum($voynum);
            $this->voyages->deleteByVoynum($voynum);
        });
    }

    /**
     * @return array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool, can_edit_sailing: bool, can_print: bool, can_rates: bool}
     */
    private function permissionsFor(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                'can_add' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_view' => true,
                'can_edit_sailing' => true,
                'can_print' => true,
                'can_rates' => true,
            ];
        }

        $perms = $this->voyages->menuPermissions((string) $user->usrcde);
        if ($perms === null) {
            return [
                'can_add' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_view' => true,
                'can_edit_sailing' => strtoupper((string) $user->usrlvl) !== 'USER',
                'can_print' => false,
                'can_rates' => $user->isAdmin(),
            ];
        }

        return [
            'can_add' => $perms['allow_add'],
            'can_edit' => $perms['allow_edit'],
            'can_delete' => $perms['allow_delete'],
            'can_view' => $perms['allow_view'],
            'can_edit_sailing' => strtoupper((string) $user->usrlvl) !== 'USER',
            'can_print' => $perms['allow_print'],
            'can_rates' => $user->isAdmin() || $perms['allow_rates'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFromRequest(array $validated): array
    {
        $dstcde = Text::clip($validated['dstcde'] ?? '', 50);
        $trndte = $this->dateOrNull($validated['trndte'] ?? null);
        $etadte = $this->dateOrNull($validated['etadte'] ?? null) ?? $trndte;

        return [
            'voynum' => Text::clip($validated['voynum'] ?? '', 15),
            'vsslcde' => Text::clip($validated['vsslcde'] ?? '', 30),
            'trndte' => $trndte,
            'etadte' => $etadte,
            'origin' => Text::clip($validated['origin'] ?? '', 30),
            'dstcde' => $dstcde,
            'dstdsc' => Text::clip($this->voyages->destinationDescription($dstcde), 100),
            'voytype' => Text::clip($validated['voytype'] ?? '', 50),
        ];
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return null;
        }

        return substr($text, 0, 10);
    }

    private function duplicateVoyageId(string $original): string
    {
        if (str_contains($original, '-')) {
            $parts = explode('-', $original);
            $parts[0] = $parts[0].'CF';

            return implode('-', $parts);
        }

        $withCf = preg_replace('/^([a-zA-Z]+)/', '$1CF', $original);
        if (! is_string($withCf) || $withCf === $original) {
            return $original.'CF';
        }

        return $withCf;
    }
}
