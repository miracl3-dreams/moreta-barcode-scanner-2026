<?php

namespace App\Services;

use App\Models\User;
use App\Models\VanTransportation;
use App\Repositories\VanTransportationRepository;
use App\Support\CrudList;
use App\Support\MenuPermission;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VanTransportationService
{
    private const MENPRG = 'view_van_transportation.php';

    public function __construct(private VanTransportationRepository $transports) {}

    /**
     * @return array{
     *     destinations: list<array{dstcde: string, dstdsc: string}>,
     *     permissions: array{can_add: bool, can_edit: bool, can_delete: bool, can_view: bool}
     * }
     */
    public function lookups(User $user): array
    {
        return [
            'destinations' => $this->transports->destinations(),
            'permissions' => $this->permissionsFor($user),
        ];
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchPayers(string $type, string $term): array
    {
        $kind = strtolower($type) === 'consignee' ? 'consignee' : 'shipper';

        return $this->transports->searchPayers($kind, $term);
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function searchVans(string $term): array
    {
        return $this->transports->searchVans($term);
    }

    /**
     * @return array{data: mixed, meta: array<string, int>}
     */
    public function list(string $search, mixed $perPage, string $sort = '', string $dir = 'asc'): array
    {
        $pageSize = CrudList::perPage($perPage);
        if ($pageSize === 'all') {
            return CrudList::fromCollection($this->transports->getForList($search, $sort, $dir));
        }

        return CrudList::fromPaginator($this->transports->paginateForList($search, $pageSize, $sort, $dir));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $user): VanTransportation
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_ADD);

        return DB::transaction(function () use ($validated, $user) {
            $docnum = Text::clip($this->transports->nextDocnum(), 25);
            if ($docnum === '') {
                throw ValidationException::withMessages([
                    'docnum' => ['Missing Doc. No.'],
                ]);
            }

            $row = $this->transports->create($this->attributesFromRequest($validated, $user, $docnum));
            $this->syncVanLocation($row, $this->asOfDate($row));

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(VanTransportation $row, array $validated, User $user): VanTransportation
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        return DB::transaction(function () use ($row, $validated, $user) {
            $docnum = trim((string) ($row->docnum ?? ''));
            $saved = $this->transports->save($row, $this->attributesFromRequest($validated, $user, $docnum));
            $this->syncVanLocation($saved, $this->asOfDate($saved));

            return $saved;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function returnVan(VanTransportation $row, array $validated, User $user): VanTransportation
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_EDIT);

        $dteout = $this->datePart($row->dteout);
        $dteret = $this->datePart($validated['dteret'] ?? '');
        if ($dteret === '') {
            throw ValidationException::withMessages([
                'dteret' => ['Date Return must be greater than date out.'],
            ]);
        }
        if ($dteout !== '' && $dteret < $dteout) {
            throw ValidationException::withMessages([
                'dteret' => ['Date Return must be greater than date out.'],
            ]);
        }

        $dstcde = Text::clip($validated['dstcde'] ?? '', 50);
        if ($dstcde === '') {
            $dstcde = Text::clip($row->origin ?? '', 30);
        }

        return DB::transaction(function () use ($row, $dteret, $dstcde) {
            $saved = $this->transports->save($row, [
                'dteret' => $dteret,
                'dstcde' => $dstcde,
                'vt_status' => 'RETURNED',
            ]);
            $this->syncVanLocation($saved, $dteret);

            return $saved;
        });
    }

    public function delete(VanTransportation $row, User $user): void
    {
        MenuPermission::assert($user, self::MENPRG, MenuPermission::ACTION_DELETE);

        DB::transaction(function () use ($row) {
            $vannum = trim((string) ($row->vannum ?? ''));
            $this->transports->delete($row);
            if ($vannum === '') {
                return;
            }

            $asOf = now('Asia/Manila')->format('Y-m-d H:i:s');
            $this->applyVanLocation($vannum, $asOf, 'VT', '');
        });
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

        $perms = $this->transports->menuPermissions((string) $user->usrcde);
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
    private function attributesFromRequest(array $validated, User $user, string $docnum): array
    {
        $payee = trim((string) ($validated['payee'] ?? 'shipper')) === 'consignee' ? 'consignee' : 'shipper';
        $dteout = $this->datePart($validated['dteout'] ?? '');
        $dteret = $this->datePart($validated['dteret'] ?? '');
        $returned = $dteret !== '';

        if ($payee === 'consignee') {
            $cuscde = '';
            $cusdsc = '';
            $concde = Text::clip($validated['concde'] ?? '', 50);
            $condsc = Text::clip($validated['condsc'] ?? '', 100);
        } else {
            $cuscde = Text::clip($validated['cuscde'] ?? '', 100);
            $cusdsc = Text::clip($validated['cusdsc'] ?? '', 100);
            $concde = '';
            $condsc = '';
        }

        return [
            'docnum' => $docnum,
            'usrnam' => Text::clip($user->usrname ?? $user->usrcde ?? '', 20),
            'trncde' => 'VT',
            'trndte' => $returned ? $dteret : ($dteout !== '' ? $dteout : null),
            'dteout' => $dteout !== '' ? $dteout : null,
            'dteret' => $returned ? $dteret : null,
            'vt_status' => $returned ? 'RETURNED' : 'BORROWED',
            'payee' => $payee,
            'cuscde' => $cuscde,
            'cusdsc' => $cusdsc,
            'concde' => $concde,
            'condsc' => $condsc,
            'vannum' => Text::clip($validated['vannum'] ?? '', 30),
            'eir' => Text::clip($validated['eir'] ?? '', 20),
            'origin' => Text::clip($validated['origin'] ?? '', 30),
            'dstcde' => Text::clip($validated['dstcde'] ?? '', 50),
        ];
    }

    private function syncVanLocation(VanTransportation $row, string $asOf): void
    {
        $vannum = trim((string) ($row->vannum ?? ''));
        if ($vannum === '') {
            return;
        }

        $payeeCode = trim((string) ($row->payee ?? '')) === 'consignee'
            ? trim((string) ($row->concde ?? ''))
            : trim((string) ($row->cuscde ?? ''));
        $remarks = trim((string) ($row->vt_status ?? '')).' BY : '.$payeeCode;

        $this->applyVanLocation($vannum, $asOf, 'VT', $remarks);
    }

    private function applyVanLocation(string $vannum, string $asOf, string $trncde, string $remarks): void
    {
        $lastloc = $this->transports->vanLastLocation($vannum, $asOf);
        if ($lastloc === null) {
            return;
        }

        $columns = $this->transports->vanLocationColumns();
        $update = ['lastloc' => Text::clip($lastloc, 100)];
        if ($columns['trncde']) {
            $update['trncde'] = Text::clip($trncde, 3);
        }
        if ($columns['remarksloc'] && $remarks !== '') {
            $update['remarksloc'] = Text::clip($remarks, 100);
        }

        $this->transports->updateVanLocation($vannum, $update);
    }

    private function asOfDate(VanTransportation $row): string
    {
        $dteret = $this->datePart($row->dteret);
        if ($dteret !== '') {
            return $dteret;
        }

        return $this->datePart($row->dteout);
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
