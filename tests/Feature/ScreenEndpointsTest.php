<?php

use Laravel\Sanctum\Sanctum;

/**
 * Every screen's read endpoint must actually answer.
 *
 * These features shipped backend-first, so the failure mode worth guarding is a
 * page wired to a route that throws on an empty or realistic database — a
 * mistyped column shows up as a 500 here rather than as a blank screen.
 */
beforeEach(fn () => Sanctum::actingAs(admin()));

dataset('read_endpoints', [
    'qas pending' => '/api/v1/qas/pending',
    'crp list' => '/api/v1/crp',
    'kanban list' => '/api/v1/kanbans',
    'fcs list' => '/api/v1/fcs',
    'fcs eligible' => '/api/v1/fcs/eligible-wos',
    'fg transfer lots' => '/api/v1/fg-transfer/lots',
    'fg downgrade mappings' => '/api/v1/fg-downgrade/mappings',
    'whs master' => '/api/v1/whs-items',
    'whs po' => '/api/v1/whs-po',
    'whs incoming' => '/api/v1/whs-incoming',
    'whs incoming open pos' => '/api/v1/whs-incoming/open-pos',
    'whs outgoing' => '/api/v1/whs-outgoing',
    'whs outgoing items' => '/api/v1/whs-outgoing/available-items',
    'whs returns' => '/api/v1/whs-returns',
    'whs on loan' => '/api/v1/whs-returns/on-loan',
    'whs stock' => '/api/v1/whs-stock',
    'putaway serials' => '/api/v1/putaway/serials',
    'bom tools list' => '/api/v1/bom-tools/list',
    'mes snapshot' => '/api/v1/mes/snapshot',
    'mes sync failed' => '/api/v1/mes/sync-failed',
]);

it('answers screen read endpoints', function (string $url) {
    $this->getJson($url)->assertOk();
})->with('read_endpoints');

it('answers forecast analysis for a period range', function () {
    $this->getJson('/api/v1/forecast-analysis?period_from=202601&period_to=202612')
        ->assertOk()
        ->assertJsonStructure(['data' => ['mape', 'bias', 'items']]);
});

it('answers the tax export preview', function () {
    $this->getJson('/api/v1/tax-export/preview?from=2026-07-01&to=2026-07-31')
        ->assertOk()
        ->assertJsonStructure(['data' => ['efaktur', 'ebupot23']]);
});
