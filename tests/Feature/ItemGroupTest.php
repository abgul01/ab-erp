<?php

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Golongan barang (Material / FG) dan bentuk material.
 *
 * Keduanya sempat berebut satu kolom: `m_item.type` menyimpan golongan — dibaca
 * dashboard dan dipakai NPD untuk menaruh baris BOM — tetapi layar Item Master
 * menuliskan bentuk ke sana. Menyimpan satu item lewat layar itu mengubah
 * golongannya menjadi "Roundbar", dan sejak itu ia berhenti terhitung sebagai
 * bahan baku di mana pun.
 */
function itemPayload(array $over = []): array
{
    return array_merge([
        'code' => 'IT-UJI-'.substr(uniqid(), -6),
        'part_name' => 'Item uji golongan',
        'group' => 'MATERIAL',
        'shape' => 'Roundbar',
        'pm' => false,
        'active' => true,
    ], $over);
}

it('menyimpulkan bahan baku dari Material tanpa centang PM', function () {
    Sanctum::actingAs(admin());

    $item = $this->postJson('/api/v1/items', itemPayload())->assertCreated()->json('data');

    expect($item['type'])->toBe('RM')
        // Bentuk tersimpan di kolomnya sendiri, tidak menimpa golongan.
        ->and($item['shape'])->toBe('Roundbar')
        ->and($item['group'])->toBe('MATERIAL');
});

it('menyimpulkan komponen dari Material yang dicentang PM', function () {
    Sanctum::actingAs(admin());

    $item = $this->postJson('/api/v1/items', itemPayload(['pm' => true]))->assertCreated()->json('data');

    expect($item['type'])->toBe('PM');
});

it('menyimpulkan barang jadi dari Group FG, apa pun centang PM-nya', function () {
    Sanctum::actingAs(admin());

    // "FG yang dicentang PM" tidak berarti apa-apa; FG tetap FG.
    $item = $this->postJson('/api/v1/items', itemPayload(['group' => 'FG', 'pm' => true]))
        ->assertCreated()->json('data');

    expect($item['type'])->toBe('FG');
});

it('menolak Group yang tidak dikenal', function () {
    Sanctum::actingAs(admin());

    $this->postJson('/api/v1/items', itemPayload(['group' => 'Roundbar']))->assertStatus(422);
    $this->postJson('/api/v1/items', itemPayload(['group' => '']))->assertStatus(422);
});

it('tidak lagi merusak golongan saat item lama disimpan ulang', function () {
    Sanctum::actingAs(admin());

    // Item bahan baku yang sudah ada, seperti hasil seeder.
    $id = DB::table('m_item')->where('type', 'RM')->value('id');
    $before = DB::table('m_item')->where('id', $id)->first();

    $this->putJson("/api/v1/items/{$id}", itemPayload([
        'code' => $before->code,
        'part_name' => $before->part_name,
        'group' => 'MATERIAL',
        'shape' => 'Pipe',
        'pm' => (bool) $before->pm,
    ]))->assertOk();

    $after = DB::table('m_item')->where('id', $id)->first();

    // Inilah kerusakan yang dulu terjadi: golongan berubah jadi bentuk.
    expect($after->type)->toBe('RM')
        ->and($after->shape)->toBe('Pipe');
});

it('menyelaraskan kategori master dengan golongan', function () {
    Sanctum::actingAs(admin());

    $fg = $this->postJson('/api/v1/items', itemPayload(['group' => 'FG']))->assertCreated()->json('data');
    $rm = $this->postJson('/api/v1/items', itemPayload())->assertCreated()->json('data');

    $fgCat = DB::table('m_i_category')->where('id', $fg['category_id'])->value('name_c');
    $rmCat = DB::table('m_i_category')->where('id', $rm['category_id'])->value('name_c');

    // Dua sumber kebenaran untuk hal yang sama pasti berbeda suatu hari, jadi
    // kategori mengikuti golongan.
    expect($fgCat)->toContain('(FG)')
        ->and($rmCat)->toContain('(RM)');
});

it('membiarkan bentuk kosong — tidak semua barang punya bentuk batangan', function () {
    Sanctum::actingAs(admin());

    $item = $this->postJson('/api/v1/items', itemPayload(['group' => 'FG', 'shape' => null]))
        ->assertCreated()->json('data');

    expect($item['shape'])->toBeNull()
        ->and($item['type'])->toBe('FG');
});
