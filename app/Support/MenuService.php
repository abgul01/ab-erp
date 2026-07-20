<?php

namespace App\Support;

use App\Models\menus;
use App\Models\User;

/**
 * Builds the dynamic sidebar tree + permission map for a user (PRD 2.3 / LLD 8.2).
 * Super admin sees every menu with full rights; others only menus they can view.
 */
class MenuService
{
    /**
     * Hierarchical menu tree (parent_id = 0 at root) scoped to what the user may view.
     */
    public static function treeFor(User $user): array
    {
        $all = menus::orderBy('id')->get();

        if ($user->isSuperAdmin()) {
            $viewable = $all->pluck('id')->all();
        } else {
            $viewable = $user->permissions()
                ->where('can_view', 1)
                ->pluck('menu_id')
                ->all();
        }

        $viewable = array_flip($viewable);

        // Keep a parent if it (or any descendant) is viewable.
        $build = function ($parentId) use (&$build, $all, $viewable) {
            $branch = [];
            foreach ($all->where('parent_id', $parentId) as $menu) {
                $children = $build($menu->id);
                $selfViewable = isset($viewable[$menu->id]);
                if ($selfViewable || ! empty($children)) {
                    $branch[] = [
                        'id' => $menu->id,
                        'name' => $menu->name,
                        'link' => $menu->link,
                        'icon' => $menu->icon,
                        'children' => $children,
                    ];
                }
            }

            return $branch;
        };

        return $build(0);
    }

    /**
     * Flat map keyed by menu link → allowed actions, consumed by the SPA <Can> guard.
     * e.g. { "items": { "view": true, "create": true, ... } }
     */
    public static function permissionMap(User $user): array
    {
        if ($user->isSuperAdmin()) {
            $map = [];
            foreach (menus::whereNotNull('link')->where('link', '!=', '0')->get() as $menu) {
                $map[$menu->link] = self::fullRights();
            }

            return $map;
        }

        $map = [];
        $perms = $user->permissions()->with('menu')->get();
        foreach ($perms as $p) {
            if (! $p->menu || ! $p->menu->link) {
                continue;
            }
            $map[$p->menu->link] = [
                'view' => (bool) $p->can_view,
                'create' => (bool) $p->can_create,
                'edit' => (bool) $p->can_edit,
                'delete' => (bool) $p->can_delete,
                'download' => (bool) $p->can_download,
                'import' => (bool) $p->can_import,
            ];
        }

        return $map;
    }

    private static function fullRights(): array
    {
        return [
            'view' => true,
            'create' => true,
            'edit' => true,
            'delete' => true,
            'download' => true,
            'import' => true,
        ];
    }
}
