<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Models are pinned to the `mysql` connection over the preserved legacy schema,
| so feature tests run against the seeded dev database wrapped in a transaction
| that is rolled back after each test — no pollution, no migration refresh.
*/

pest()->extend(TestCase::class)
    ->beforeEach(function () {
        config(['database.default' => 'mysql']);
        DB::connection('mysql')->beginTransaction();
    })
    ->afterEach(function () {
        DB::connection('mysql')->rollBack();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/** The seeded super-admin (created if missing). */
function admin(): User
{
    return User::firstOrCreate(
        ['username' => 'admin'],
        ['name' => 'Administrator', 'email' => 'admin@ab-erp.local', 'identity' => 'ADM-0001', 'password' => bcrypt('password')]
    );
}

/** Build a Request bound to the admin user, as controllers receive it. */
function req(string $method = 'GET', array $params = []): Request
{
    $r = Request::create('/test', $method, $params);
    $r->setUserResolver(fn () => admin());

    return $r;
}

/** Unwrap an ApiResponse JsonResponse to its `data` payload. */
function payload($response): mixed
{
    $json = json_decode($response->getContent(), true);

    return $json['data'] ?? $json;
}

/** Ensure a COA account exists (for accounting tests). */
function coa(string $code, string $group = 'ASSET'): void
{
    DB::table('acc_coa')->updateOrInsert(['code' => $code], ['name' => "Test {$code}", 'acc_group' => $group, 'postable' => 1]);
}

expect()->extend('toBeMoney', function (float $expected) {
    return $this->toEqualWithDelta($expected, 0.02);
});
