<?php

namespace Database\Seeders;

use App\Models\menus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent: menambah menu "Manajemen User" & "Manajemen Menu" di bawah
 * grup Administrator, tanpa menyentuh password user mana pun. Aman dijalankan
 * berulang. FoundationSeeder sudah memuat menu yang sama untuk fresh install;
 * seeder ini ada untuk database yang sudah berjalan tanpa menjalankan ulang
 * seluruh FoundationSeeder (yang akan mereset password seeder).
 */
class AdminUserManagementSeeder extends Seeder
{
    public function run(): void
    {
        $parent = menus::firstOrCreate(
            ['name' => 'Administrator', 'parent_id' => 0],
            ['link' => '0', 'icon' => 'shield', 'sort' => 0]
        );

        $children = [
            ['Manajemen User', 'users', 'users', 1],
            ['Manajemen Menu', 'menus', 'layers', 2],
        ];

        foreach ($children as [$name, $link, $icon, $sort]) {
            menus::updateOrCreate(
                ['link' => $link],
                ['name' => $name, 'parent_id' => $parent->id, 'icon' => $icon, 'sort' => $sort]
            );
        }

        // Operator (dan akun lain yang sudah terdaftar) tidak punya akses ke
        // menu administrasi — hak ini hanya dipegang super admin.
        foreach (['users', 'menus'] as $link) {
            $menuId = menus::where('link', $link)->value('id');
            if (! $menuId) {
                continue;
            }

            foreach (User::where('username', '!=', 'admin')->pluck('id') as $userId) {
                DB::table('user_menu_permissions')->updateOrInsert(
                    ['user_id' => $userId, 'menu_id' => $menuId],
                    [
                        'can_view' => 0,
                        'can_create' => 0,
                        'can_edit' => 0,
                        'can_delete' => 0,
                        'can_download' => 0,
                        'can_import' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command?->info('  Admin User Management: menu Users & Menus siap.');
    }
}
