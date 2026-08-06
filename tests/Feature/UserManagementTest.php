<?php

use App\Models\menus;
use App\Models\status_id;
use App\Models\User;
use App\Models\user_menu_permissions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/**
 * Manajemen user & menu (PRD 2.4 Module Administrator):
 * registrasi user baru, ganti/reset password, hak akses per user, CRUD menu.
 */
function staffUser(string $status = 'ACTIVE'): User
{
    return User::create([
        'username' => 'staf-'.substr(uniqid(), -8),
        'name' => 'Staf Test',
        'email' => uniqid().'@staff.test',
        'identity' => 'STF-'.substr(uniqid(), -5),
        'password' => 'password123',
        'status_id' => status_id::where('status', $status)->value('id'),
    ]);
}

function menuId(string $link): int
{
    return (int) menus::where('link', $link)->value('id');
}

it('membuat user baru (registrasi) lewat API', function () {
    Sanctum::actingAs(admin());
    $username = 'reg-'.substr(uniqid(), -6);

    $r = $this->postJson('/api/v1/users', [
        'username' => $username,
        'name' => 'Registrasi Baru',
        'email' => uniqid().'@reg.test',
        'identity' => 'REG-'.substr(uniqid(), -5),
        'password' => 'password123',
        'status_id' => status_id::where('status', 'ACTIVE')->value('id'),
    ]);

    $r->assertStatus(201);
    expect($r->json('data.username'))->toBe($username)
        ->and(Hash::check('password123', User::where('username', $username)->value('password')))->toBeTrue();
});

it('menolak username/email/identity ganda', function () {
    Sanctum::actingAs(admin());
    $user = staffUser();

    $this->postJson('/api/v1/users', [
        'username' => $user->username,
        'name' => 'Dup',
        'password' => 'password123',
    ])->assertStatus(422)->assertJsonPath('errors.0.code', 'VALIDATION');

    $this->postJson('/api/v1/users', [
        'username' => 'du-'.substr(uniqid(), -6),
        'name' => 'Dup',
        'email' => $user->email,
        'password' => 'password123',
    ])->assertStatus(422);
});

it('anonim 401 dan operator tanpa hak 403 untuk manajemen user', function () {
    $this->getJson('/api/v1/users')->assertStatus(401);

    $operator = staffUser();
    Sanctum::actingAs($operator);

    $this->getJson('/api/v1/users')->assertStatus(403);
    $this->postJson('/api/v1/users', [
        'username' => 'x-'.substr(uniqid(), -6), 'name' => 'X', 'password' => 'password123',
    ])->assertStatus(403);
});

it('menyimpan matriks hak akses (full replace)', function () {
    Sanctum::actingAs(admin());
    $user = staffUser();
    $items = menuId('items');
    $po = menuId('po');

    $this->putJson("/api/v1/users/{$user->id}/permissions", [
        'permissions' => [
            ['menu_id' => $items, 'can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => false, 'can_download' => true, 'can_import' => false],
            ['menu_id' => $po, 'can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'can_download' => false, 'can_import' => false],
        ],
    ])->assertStatus(200);

    $row = user_menu_permissions::where('user_id', $user->id)->where('menu_id', $items)->first();
    expect($row)->not->toBeNull()
        ->and($row->can_view)->toBe(1)
        ->and($row->can_create)->toBe(1)
        ->and($row->can_delete)->toBe(0);

    // full replace: daftar kedua menggantikan daftar pertama
    $this->putJson("/api/v1/users/{$user->id}/permissions", [
        'permissions' => [
            ['menu_id' => $po, 'can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'can_download' => false, 'can_import' => false],
        ],
    ])->assertStatus(200);

    expect(user_menu_permissions::where('user_id', $user->id)->count())->toBe(1)
        ->and(user_menu_permissions::where('user_id', $user->id)->where('menu_id', $items)->exists())->toBeFalse();
});

it('matriks hak akses super admin ditolak (423)', function () {
    Sanctum::actingAs(admin());

    $this->putJson('/api/v1/users/'.admin()->id.'/permissions', ['permissions' => []])
        ->assertStatus(423)
        ->assertJsonPath('errors.0.code', 'ADMIN_USER_LOCKED');
});

it('reset password admin: token lama user mati, user bisa login dengan password baru', function () {
    Sanctum::actingAs(admin());
    $user = staffUser();
    $oldToken = $user->createToken('old')->plainTextToken;

    $this->postJson("/api/v1/users/{$user->id}/password", [
        'password' => 'barubaru9',
        'password_confirmation' => 'barubaru9',
    ])->assertStatus(200);

    expect(Hash::check('barubaru9', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'barubaru9'])
        ->assertStatus(200)
        ->assertJsonPath('data.user.username', $user->username);

    $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password123'])
        ->assertStatus(422);
});

it('ganti password sendiri: password lama salah ditolak', function () {
    Sanctum::actingAs(admin());

    $this->postJson('/api/v1/auth/change-password', [
        'current_password' => 'salah',
        'password' => 'barubaru9',
        'password_confirmation' => 'barubaru9',
    ])->assertStatus(422)->assertJsonPath('errors.0.field', 'current_password');

    expect(Hash::check('password', admin()->fresh()->password))->toBeTrue();
});

it('ganti password sendiri: sukses dengan password lama benar', function () {
    $admin = admin();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/auth/change-password', [
        'current_password' => 'password',
        'password' => 'barubaru9',
        'password_confirmation' => 'barubaru9',
    ])->assertStatus(200);

    expect(Hash::check('barubaru9', $admin->fresh()->password))->toBeTrue();
});

it('menghapus user beserta hak akses dan tokennya', function () {
    Sanctum::actingAs(admin());
    $user = staffUser();
    $user->createToken('tok');
    user_menu_permissions::create(['user_id' => $user->id, 'menu_id' => menuId('items'), 'can_view' => 1]);

    $this->deleteJson("/api/v1/users/{$user->id}")->assertStatus(200);

    expect(User::find($user->id))->toBeNull()
        ->and(user_menu_permissions::where('user_id', $user->id)->exists())->toBeFalse();
});

it('menolak menghapus akun sendiri dan super admin', function () {
    $admin = admin();
    Sanctum::actingAs($admin);

    $this->deleteJson('/api/v1/users/'.$admin->id)
        ->assertStatus(409)
        ->assertJsonPath('errors.0.code', 'ADMIN_USER_LOCKED');
});

it('menolak menonaktifkan atau mengganti username super admin', function () {
    Sanctum::actingAs(admin());
    $admin = admin();

    $this->putJson('/api/v1/users/'.$admin->id, [
        'username' => 'hacker', 'name' => 'Administrator',
        'status_id' => status_id::where('status', 'INACTIVE')->value('id'),
    ])->assertStatus(423)->assertJsonPath('errors.0.code', 'ADMIN_USER_LOCKED');

    expect($admin->fresh()->username)->toBe('admin');
});

it('menu: index menampilkan seluruh pohon dan jumlah anak', function () {
    Sanctum::actingAs(admin());

    $this->getJson('/api/v1/menus')
        ->assertStatus(200)
        ->assertJsonCount(menus::count(), 'data')
        ->assertJsonPath('data.0.link', '0');
});

it('menu: membuat dan menghapus menu, hak akses ikut terhapus', function () {
    Sanctum::actingAs(admin());
    $parent = (int) menus::where('parent_id', 0)->value('id');
    $user = staffUser();

    $created = $this->postJson('/api/v1/menus', [
        'name' => 'Menu Tes '.substr(uniqid(), -4),
        'link' => 'menu-tes-'.substr(uniqid(), -6),
        'parent_id' => $parent,
        'icon' => 'box',
        'sort' => 99,
    ])->assertStatus(201)->json('data');

    user_menu_permissions::create(['user_id' => $user->id, 'menu_id' => $created['id'], 'can_view' => 1]);

    $this->deleteJson('/api/v1/menus/'.$created['id'])->assertStatus(200);

    expect(menus::find($created['id']))->toBeNull()
        ->and(user_menu_permissions::where('menu_id', $created['id'])->exists())->toBeFalse();
});

it('menu: menolak parent diri sendiri / keturunan dan hapus menu beranak', function () {
    Sanctum::actingAs(admin());
    $parent = (int) menus::where('parent_id', 0)->value('id');
    $child = menus::create(['name' => 'Anak Tes', 'link' => 'anak-'.substr(uniqid(), -6), 'parent_id' => $parent, 'sort' => 0]);

    $this->putJson('/api/v1/menus/'.$child->id, ['name' => $child->name, 'parent_id' => $child->id])
        ->assertStatus(422)->assertJsonPath('errors.0.code', 'MENU_LOOP');

    $this->deleteJson('/api/v1/menus/'.$parent)
        ->assertStatus(409)->assertJsonPath('errors.0.code', 'MENU_HAS_CHILDREN');

    $this->deleteJson('/api/v1/menus/'.$child->id)->assertStatus(200);
});

it('login menghadirkan menu Manajemen User untuk super admin', function () {
    $r = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'password']);
    $r->assertStatus(200);

    $links = collect($r->json('data.menus'))->flatMap(fn ($g) => array_merge(
        [$g['link']],
        collect($g['children'] ?? [])->pluck('link')->all(),
    ));

    expect($links)->toContain('users')->toContain('menus');
});
