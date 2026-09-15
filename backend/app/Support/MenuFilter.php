<?php

namespace App\Support;

use Illuminate\Support\Collection;

class MenuFilter
{
    /** @var list<string> */
    private const EXCLUDED_GROUP_CAPTIONS = [
        'OTHERS',
    ];

    public static function isExcludedGroup(string $caption): bool
    {
        return in_array(strtoupper(trim($caption)), self::EXCLUDED_GROUP_CAPTIONS, true);
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    public static function filterSidebarGroups(array $groups): array
    {
        return array_values(array_filter($groups, function (array $group): bool {
            if (self::isExcludedGroup((string) ($group['caption'] ?? ''))) {
                return false;
            }

            $itemCount = collect($group['items'] ?? [])
                ->where('type', 'item')
                ->count();

            return $itemCount > 0;
        }));
    }

    /**
     * Drop excluded top-level groups and their child rows from flat menu lists.
     *
     * @param  Collection<int, object>  $menus
     * @return Collection<int, object>
     */
    public static function filterFlatMenus(Collection $menus): Collection
    {
        $filtered = collect();
        $skipGroup = false;

        foreach ($menus as $menu) {
            $level = (int) ($menu->menlvl ?? 0);

            if ($level === 1) {
                $skipGroup = self::isExcludedGroup((string) ($menu->mencap ?? ''));

                if ($skipGroup) {
                    continue;
                }
            } elseif ($skipGroup) {
                continue;
            }

            $filtered->push($menu);
        }

        return $filtered->values();
    }
}
