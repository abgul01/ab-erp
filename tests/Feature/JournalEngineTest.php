<?php

use App\Exceptions\BizException;
use App\Support\JournalEngine;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    coa('1100', 'ASSET');
    coa('4100', 'REVENUE');
});

it('posts a balanced entry and auto-opens the period', function () {
    $jrn = JournalEngine::post('TEST_SALE', 777001, '2099-12-15', 'AR', [
        ['coa' => '1100', 'debit' => 100000],
        ['coa' => '4100', 'credit' => 100000],
    ], 'balanced entry', admin()->id);

    expect($jrn)->not->toBeNull()
        ->and($jrn->period)->toBe('209912')
        ->and($jrn->status)->toBe('POSTED')
        ->and(DB::table('acc_journal_det')->where('main_id', $jrn->id)->count())->toBe(2)
        ->and(DB::table('acc_period')->where('period', '209912')->value('status'))->toBe('OPEN');
});

it('rejects an unbalanced entry', function () {
    JournalEngine::post('TEST_SALE', 777002, '2099-12-15', 'AR', [
        ['coa' => '1100', 'debit' => 100000],
        ['coa' => '4100', 'credit' => 90000],
    ], 'unbalanced', admin()->id);
})->throws(BizException::class);

it('refuses posting into a closed period', function () {
    DB::table('acc_period')->updateOrInsert(['period' => '209911'], ['status' => 'CLOSED']);

    JournalEngine::post('TEST_SALE', 777003, '2099-11-15', 'AR', [
        ['coa' => '1100', 'debit' => 100000],
        ['coa' => '4100', 'credit' => 100000],
    ], 'into closed period', admin()->id);
})->throws(BizException::class);

it('is idempotent on (ref_type, ref_id, jrn_type)', function () {
    $lines = [['coa' => '1100', 'debit' => 50000], ['coa' => '4100', 'credit' => 50000]];

    $first = JournalEngine::post('TEST_SALE', 777004, '2099-12-15', 'AR', $lines, 'first', admin()->id);
    $again = JournalEngine::post('TEST_SALE', 777004, '2099-12-15', 'AR', $lines, 'second', admin()->id);

    expect($again->id)->toBe($first->id)
        ->and(DB::table('acc_journal_main')->where('ref_type', 'TEST_SALE')->where('ref_id', 777004)->count())->toBe(1);
});

it('reverses a posted journal with swapped debit/credit', function () {
    $jrn = JournalEngine::post('TEST_SALE', 777005, '2099-12-15', 'AR', [
        ['coa' => '1100', 'debit' => 70000],
        ['coa' => '4100', 'credit' => 70000],
    ], 'to reverse', admin()->id);

    $rev = JournalEngine::reverse($jrn, admin()->id);

    expect($rev->jrn_type)->toBe('REV')
        ->and($jrn->fresh()->status)->toBe('REVERSED')
        ->and((float) DB::table('acc_journal_det')->where('main_id', $rev->id)->where('coa_id', DB::table('acc_coa')->where('code', '1100')->value('id'))->value('credit'))->toBe(70000.0);
});
