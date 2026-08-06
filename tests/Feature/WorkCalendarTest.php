<?php

use App\Support\CrpService;
use App\Support\FgStockService;
use App\Support\MrpService;
use App\Support\WorkCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * The working calendar decides when the plant runs, and both the production
 * schedule and the capacity figure lean on it. Getting it wrong does not throw
 * an error — it quietly produces a plan for days the factory is shut.
 */
function calPeriod(): string
{
    return '209905';   // far enough out that no seeded data collides
}

it('falls back to weekdays when a month has no calendar', function () {
    DB::table('m_work_calendar')->whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [calPeriod()])->delete();

    $days = (new WorkCalendarService)->daysIn(calPeriod());

    // May 2099 has 31 days, 21 of them weekdays.
    expect($days)->toHaveCount(21)
        ->and(reset($days))->toBe(WorkCalendarService::DEFAULT_HOURS);
});

it('excludes holidays once the calendar is filled in', function () {
    $svc = new WorkCalendarService;
    $svc->generate(calPeriod(), ['2099-05-04', '2099-05-05']);

    $days = $svc->daysIn(calPeriod());

    // Two working days removed by the holidays.
    expect($days)->toHaveCount(19)
        ->and($days)->not->toHaveKey('2099-05-04')
        ->and($svc->isWorkingDay('2099-05-04'))->toBeFalse()
        ->and($svc->isWorkingDay('2099-05-06'))->toBeTrue();
});

it('can put Saturdays to work', function () {
    $svc = new WorkCalendarService;
    $svc->generate(calPeriod(), [], 16.0, saturdayWorks: true);

    // 31 days less five Sundays.
    expect($svc->daysIn(calPeriod()))->toHaveCount(26);
});

it('counts machine capacity from the calendar, not a fixed month', function () {
    $svc = new WorkCalendarService;
    $svc->generate(calPeriod(), ['2099-05-04']);          // 20 working days

    $twoShift = DB::table('m_machine')->where('active', 1)->value('id');
    DB::table('m_machine')->where('id', $twoShift)->update(['daily_hours' => 0]);

    $oneShift = DB::table('m_machine')->where('active', 1)->where('id', '!=', $twoShift)->value('id');
    DB::table('m_machine')->where('id', $oneShift)->update(['daily_hours' => 8]);

    expect($svc->machineHours($twoShift, calPeriod()))->toBe(320.0)   // 20 × 16
        ->and($svc->machineHours($oneShift, calPeriod()))->toBe(160.0) // 20 × 8
        ->and($svc->plantHours(calPeriod()))->toBe(320.0);
});

it('gives a machine no capacity in a month the plant never runs', function () {
    $svc = new WorkCalendarService;

    // Every day marked non-working.
    $holidays = collect(range(1, 31))->map(fn ($d) => sprintf('2099-05-%02d', $d))->all();
    $svc->generate(calPeriod(), $holidays);

    expect($svc->daysIn(calPeriod()))->toBeEmpty()
        ->and($svc->plantHours(calPeriod()))->toBe(0.0);
});

it('exposes the calendar over HTTP and flags the fallback', function () {
    Sanctum::actingAs(admin());
    DB::table('m_work_calendar')->whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [calPeriod()])->delete();

    $this->getJson('/api/v1/work-calendar/effective?period='.calPeriod())
        ->assertOk()
        // Saying "this is an assumption" out loud is the point — a silent
        // fallback is how holidays get scheduled through.
        ->assertJsonPath('data.is_fallback', true)
        ->assertJsonPath('data.working_days', 21);

    $this->postJson('/api/v1/work-calendar/generate', [
        'period' => calPeriod(), 'holidays' => ['2099-05-04'],
    ])->assertOk();

    $this->getJson('/api/v1/work-calendar/effective?period='.calPeriod())
        ->assertOk()
        ->assertJsonPath('data.is_fallback', false)
        ->assertJsonPath('data.working_days', 20);
});

it('orders material early enough for its lead time', function () {
    $f = mrpFixture(['plan' => 100]);
    DB::table('m_item')->where('id', $f['rm'])->update(['lead_time_days' => 45]);

    $svc = new MrpService(app(FgStockService::class));
    $run = $svc->run([$f['period']], admin()->id);
    $result = $svc->generatePr($run->id, admin()->id);

    $line = DB::table('prc_pr_detail')->where('main_id', $result['pr_id'])->where('item_id', $f['rm'])->first();
    $neededBy = Carbon::parse(substr($f['period'], 0, 4).'-'.substr($f['period'], 4, 2).'-01');

    /*
     * Imported steel on 45-day lead time cannot be ordered on the day it is
     * wanted. Either the buyer still has time — then the requisition is dated
     * exactly 45 days before the material is needed — or that moment has
     * already passed, and it is dated today and says so. Both are correct; what
     * would be wrong is silently dating it for the day the steel is needed.
     */
    $orderBy = Carbon::parse($line->need_date);

    if ($orderBy->isToday()) {
        expect($line->note)->toContain('TERLAMBAT')
            ->and($orderBy->lt($neededBy->copy()->subDays(45)))->toBeFalse();
    } else {
        expect($orderBy->diffInDays($neededBy))->toBe(45)
            ->and($orderBy->lt($neededBy))->toBeTrue();
    }

    // The note is terse because prc_pr_detail.note is varchar(150), but the
    // lead time it was derived from still has to be visible on the line.
    expect($line->note)->toContain('LT 45h');
});

it('runs CRP against calendar capacity rather than a hardcoded month', function () {
    $svc = new WorkCalendarService;
    $svc->generate(calPeriod(), []);                      // 21 working days × 16 h

    $result = app(CrpService::class)->run(calPeriod());

    // No approved MPS that far out, so there is nothing to load — the point is
    // that it no longer reports the old fabricated 576-hour capacity.
    expect($result['loads'])->toBeArray();

    $rows = DB::table('prd_crp')->where('period', calPeriod())->get();
    foreach ($rows as $r) {
        expect((float) $r->capacity_hours)->not->toBe(576.0);
    }
});

it('applies active master holidays when generating a month', function () {
    DB::table('m_holiday')->where('date', '2099-05-04')->delete();
    DB::table('m_holiday')->insert([
        'date' => '2099-05-04', 'name' => 'Kenaikan Isa Almasih', 'type' => 'NASIONAL',
        'is_working' => 0, 'hours' => 0, 'active' => 1,
    ]);

    $svc = new WorkCalendarService;
    $svc->generate(calPeriod());

    expect($svc->daysIn(calPeriod()))->not->toHaveKey('2099-05-04')
        ->and($svc->isWorkingDay('2099-05-04'))->toBeFalse()
        ->and(DB::table('m_work_calendar')->where('date', '2099-05-04')->value('note'))->toBe('Kenaikan Isa Almasih');
});

it('lets a cuti bersama holiday run the plant with fewer hours', function () {
    DB::table('m_holiday')->where('date', '2099-05-06')->delete();
    DB::table('m_holiday')->insert([
        'date' => '2099-05-06', 'name' => 'Cuti bersama Lebaran', 'type' => 'CUTI_BERSAMA',
        'is_working' => 1, 'hours' => 8, 'active' => 1,
    ]);

    $svc = new WorkCalendarService;
    $svc->generate(calPeriod());

    expect($svc->daysIn(calPeriod()))->toHaveKey('2099-05-06', 8.0)
        ->and($svc->isWorkingDay('2099-05-06'))->toBeTrue();
});

it('ignores inactive holidays', function () {
    DB::table('m_holiday')->where('date', '2099-05-07')->delete();
    DB::table('m_holiday')->insert([
        'date' => '2099-05-07', 'name' => 'Hari pindah yang dibatalkan', 'type' => 'PERUSAHAAN',
        'is_working' => 0, 'hours' => 0, 'active' => 0,
    ]);

    $svc = new WorkCalendarService;
    $svc->generate(calPeriod());

    expect($svc->isWorkingDay('2099-05-07'))->toBeTrue();
});

it('manages the holiday master over HTTP', function () {
    Sanctum::actingAs(admin());

    $this->postJson('/api/v1/holidays', [
        'date' => '2099-05-08', 'name' => 'Hari Raya Waisak', 'type' => 'NASIONAL',
        'is_working' => false, 'hours' => 0, 'active' => true,
    ])->assertCreated()->assertJsonPath('data.name', 'Hari Raya Waisak');

    $this->getJson('/api/v1/holidays?period=209905')->assertOk()->assertJsonCount(1, 'data');

    $id = DB::table('m_holiday')->where('date', '2099-05-08')->value('id');
    $this->putJson('/api/v1/holidays/'.$id, [
        'date' => '2099-05-08', 'name' => 'Hari Raya Waisak', 'type' => 'NASIONAL',
        'is_working' => true, 'hours' => 8, 'active' => true,
    ])->assertOk()->assertJsonPath('data.is_working', true);

    $this->deleteJson('/api/v1/holidays/'.$id)->assertNoContent();
    expect(DB::table('m_holiday')->where('id', $id)->exists())->toBeFalse();
});
