<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MenuFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rows = $user->isAdmin()
            ? DB::table('menus')
                ->whereNotIn('menidx', ['x', 'X'])
                ->orderBy('menidx')
                ->get()
            : DB::table('user_menus')
                ->where('usrcde', $user->usrcde)
                ->whereNotIn('menidx', ['x', 'X'])
                ->orderBy('menidx')
                ->get();

        $iconsByProgram = DB::table('menus')
            ->where('menprg', '!=', '')
            ->pluck('menico', 'menprg');

        $groups = [];
        $current = null;

        foreach ($rows as $row) {
            $caption = trim((string) $row->mencap);
            $mengrp = (string) $row->mengrp;
            $program = trim((string) $row->menprg);
            $icon = '';
            if (isset($row->menico)) {
                $icon = trim((string) $row->menico);
            }

            if ($icon === '' && $program !== '' && isset($iconsByProgram[$program])) {
                $icon = (string) $iconsByProgram[$program];
            }
            if ($icon === '') {
                $icon = 'blank.gif';
            }

            if ($mengrp === '01') {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = [
                    'menidx' => (string) $row->menidx,
                    'caption' => $caption,
                    'items' => [],
                ];

                continue;
            }

            if ($current === null) {
                continue;
            }

            if ($caption === '') {
                $current['items'][] = [
                    'type' => 'spacer',
                    'menidx' => (string) $row->menidx,
                    'caption' => '',
                    'program' => '',
                    'icon' => $icon,
                ];

                continue;
            }

            if ($caption === '-') {
                $current['items'][] = [
                    'type' => 'separator',
                    'menidx' => (string) $row->menidx,
                    'caption' => '-',
                    'program' => '',
                    'icon' => $icon,
                ];

                continue;
            }

            $current['items'][] = [
                'type' => 'item',
                'menidx' => (string) $row->menidx,
                'caption' => $caption,
                'program' => $program,
                'icon' => $icon,
            ];
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        $groups = MenuFilter::filterSidebarGroups($groups);

        return response()->json([
            'groups' => $groups,
            'stats' => [
                'users' => (int) DB::table('userfile')->count(),
                'groups' => count($groups),
                'items' => collect($groups)->sum(function ($group) {
                    return collect($group['items'])->where('type', 'item')->count();
                }),
            ],
        ]);
    }
}
