<?php

namespace Database\Seeders;

use App\Models\npd_project;
use App\Models\npd_rfq;
use App\Support\NpdService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master NPD (5 fase APQP + deliverable wajibnya) dan beberapa proyek contoh.
 *
 * Master fase dan deliverable bukan data demo — keduanya dibutuhkan modul untuk
 * berjalan, jadi tetap disemai walau data contohnya dilewati.
 */
class NpdSeeder extends Seeder
{
    /** Lima fase APQP. */
    private const PHASES = [
        [1, 'Plan & Define', 'Intake RFQ, kebutuhan pelanggan, feasibility, tim, target, BOM awal'],
        [2, 'Product Design & Development', 'DFMEA, design review, prototype, rilis gambar & spesifikasi'],
        [3, 'Process Design & Development', 'Process flow, PFMEA, control plan, rencana MSA, layout'],
        [4, 'Product & Process Validation', 'Trial produksi, kapabilitas proses, MSA, submission PPAP'],
        [5, 'Feedback & Corrective Action', 'Serah terima ke produksi massal, pemantauan awal, perbaikan'],
    ];

    /** Deliverable baku per fase: [fase, kode, nama, wajib]. */
    private const DELIVERABLES = [
        [1, 'RFQ-REV', 'Review kebutuhan pelanggan', true],
        [1, 'FEAS', 'Feasibility study', true],
        [1, 'TEAM', 'Penetapan tim & target', true],
        [1, 'BOM-PRE', 'Preliminary BOM', true],
        [1, 'TIMELINE', 'Jadwal proyek & milestone', false],

        [2, 'DWG', 'Gambar & spesifikasi rilis', true],
        [2, 'DFMEA', 'Design FMEA', true],
        [2, 'DSGN-REV', 'Design review', true],
        [2, 'PROTO', 'Prototype', false],

        [3, 'FLOW', 'Process flow diagram', true],
        [3, 'PFMEA', 'Process FMEA', true],
        [3, 'CP', 'Control plan', true],
        [3, 'MSA-PLAN', 'Rencana MSA', false],
        [3, 'LAYOUT', 'Layout proses', false],

        [4, 'TRIAL', 'Trial produksi', true],
        [4, 'CPK', 'Studi kapabilitas proses (Cpk)', true],
        [4, 'MSA', 'Measurement System Analysis', true],
        [4, 'PPAP', 'Submission PPAP', true],
        [4, 'PSW', 'Part Submission Warrant', true],

        [5, 'HANDOVER', 'Serah terima ke produksi massal', true],
        [5, 'MONITOR', 'Pemantauan awal produksi', true],
        [5, 'LESSON', 'Lesson learned & tindakan perbaikan', false],
    ];

    /**
     * 18 elemen PPAP baku (AIAG), beserta level yang mewajibkannya.
     *
     * Level 1 hanya menuntut PSW. Level 3 dan 5 menuntut paket lengkap. Level 2
     * di tengah-tengah, dan level 4 ditentukan pelanggan — jadi yang wajib
     * padanya hanyalah PSW, sisanya dilampirkan sesuai permintaan.
     *
     * Format: [nomor, nama, level yang mewajibkan].
     */
    private const PPAP_ELEMENTS = [
        [1, 'Design Records', '2,3,5'],
        [2, 'Engineering Change Documents', '2,3,5'],
        [3, 'Customer Engineering Approval', '3,5'],
        [4, 'Design FMEA (DFMEA)', '3,5'],
        [5, 'Process Flow Diagram', '2,3,5'],
        [6, 'Process FMEA (PFMEA)', '3,5'],
        [7, 'Control Plan', '2,3,5'],
        [8, 'Measurement System Analysis (MSA)', '3,5'],
        [9, 'Dimensional Results', '2,3,5'],
        [10, 'Records of Material / Performance Tests', '2,3,5'],
        [11, 'Initial Process Studies (Cpk)', '3,5'],
        [12, 'Qualified Laboratory Documentation', '3,5'],
        [13, 'Appearance Approval Report (AAR)', '3,5'],
        [14, 'Sample Production Parts', '2,3,5'],
        [15, 'Master Sample', '3,5'],
        [16, 'Checking Aids', '3,5'],
        [17, 'Customer-Specific Requirements', '3,5'],
        // Satu-satunya yang wajib di semua level: tanpa PSW tidak ada submission.
        [18, 'Part Submission Warrant (PSW)', '1,2,3,4,5'],
    ];

    public function run(): void
    {
        $this->command?->info('Menyemai NPD (master 5 fase APQP, deliverable wajib, 18 elemen PPAP, proyek contoh)…');

        $phaseIds = $this->seedPhases();
        $this->seedDeliverableStd($phaseIds);
        $this->seedPpapElements();
        $this->seedDemoProjects();

        $this->command?->info('  NPD disemai.');
    }

    private function seedPpapElements(): void
    {
        foreach (self::PPAP_ELEMENTS as [$no, $name, $levels]) {
            SeedWriter::put('npd_ppap_std', [
                'id' => $no,
                'element_no' => $no,
                'name' => $name,
                'level_required' => $levels,
            ]);
        }
    }

    /** @return array<int, int> phase_no => id */
    private function seedPhases(): array
    {
        $ids = [];

        foreach (self::PHASES as [$no, $name, $descrip]) {
            SeedWriter::put('npd_phase', [
                'id' => $no, 'phase_no' => $no, 'name' => $name, 'descrip' => $descrip,
            ]);
            $ids[$no] = $no;
        }

        return $ids;
    }

    private function seedDeliverableStd(array $phaseIds): void
    {
        $id = 0;

        foreach (self::DELIVERABLES as [$phaseNo, $code, $name, $mandatory]) {
            SeedWriter::put('npd_deliverable_std', [
                'id' => ++$id,
                'phase_id' => $phaseIds[$phaseNo],
                'code' => $code,
                'name' => $name,
                'mandatory' => $mandatory,
                'sort' => $id,
            ]);
        }
    }

    /**
     * Tiga proyek contoh pada tahap yang berbeda, supaya layar daftar, gate, dan
     * dashboard punya isi yang masuk akal sejak pertama dibuka.
     */
    private function seedDemoProjects(): void
    {
        if (npd_project::exists()) {
            return;     // sudah ada; jangan gandakan
        }

        $customers = DB::table('m_contacts')->where('category_id', 3)->orderBy('id')->limit(3)->pluck('id');

        if ($customers->count() < 3) {
            $this->command?->warn('  Pelanggan belum cukup, proyek contoh NPD dilewati.');

            return;
        }

        $svc = app(NpdService::class);
        $rfqId = 0;

        $samples = [
            ['Bracket Tube 42mm Generasi 2', 'BRK-TUBE-42-G2', 3, 12000, 48500, 2],
            ['Exhaust Pipe Connector 60mm', 'EXH-CONN-60', 2, 6000, 92000, 1],
            ['Fuel Line Tube Assy 8mm', 'FUEL-LINE-08', 1, 24000, 31500, 1],
        ];

        foreach ($samples as $i => [$partName, $drawing, $phaseTarget, $qty, $price, $prio]) {
            $rfqDate = DemoCalendar::date(2 + $i * 3);

            $rfq = DB::table('npd_rfq')->insertGetId([
                'cus_id' => $customers[$i],
                'code' => sprintf('RFQ-2026-%03d', ++$rfqId),
                'date' => $rfqDate->toDateString(),
                'part_name' => $partName,
                'drawing_ref' => $drawing,
                'qty' => $qty,
                'target_price' => $price,
                'due_date' => $rfqDate->copy()->addMonths(4)->toDateString(),
                'status' => 'OPEN',
                'user_id' => 1,
                'created_at' => $rfqDate,
                'updated_at' => $rfqDate,
            ]);

            DB::table('npd_feasibility')->insert([
                'rfq_id' => $rfq,
                'tech_ok' => 1, 'capacity_ok' => 1, 'cost_ok' => 1,
                'material_avail' => 'Material tersedia dari supplier existing',
                'conclusion' => 'GO',
                'note' => 'Dimensi masuk kapasitas mesin cutting & bending yang ada.',
                'user_id' => 1,
                'evaluated_at' => $rfqDate->copy()->addDays(3),
                'created_at' => $rfqDate, 'updated_at' => $rfqDate,
            ]);

            $project = $svc->createFromRfq(
                npd_rfq::find($rfq),
                [
                    'name' => $partName,
                    'project_type' => $i === 2 ? 'DERIVATIVE' : 'NEW',
                    'target_sop' => $rfqDate->copy()->addMonths(5)->toDateString(),
                    'pm_user_id' => 1,
                    'priority' => ['NORMAL', 'HIGH', 'LOW'][$prio],
                ],
                1
            );

            $this->advanceTo($project, $phaseTarget, $rfqDate);
        }

        $this->command?->info('  NPD: 3 RFQ + 3 proyek contoh (fase 1, 2, dan 3).');
    }

    /**
     * Dorong proyek contoh sampai fase tertentu dengan menyelesaikan deliverable
     * wajibnya dan meloloskan gate — supaya datanya konsisten dengan aturan
     * modul, bukan status yang ditulis begitu saja.
     */
    private function advanceTo(npd_project $project, int $targetPhase, $baseDate): void
    {
        for ($no = 1; $no < $targetPhase; $no++) {
            $phase = $project->phases()->where('phase_no', $no)->first();

            $phase->deliverables()->update([
                'status' => 'DONE',
                'submitted_date' => $baseDate->copy()->addDays(10 * $no)->toDateString(),
                'updated_at' => now(),
            ]);

            // Beberapa task agar layar fase tidak kosong.
            foreach (['Kumpulkan data pelanggan', 'Review internal', 'Siapkan dokumen fase'] as $k => $name) {
                $phase->tasks()->create([
                    'name' => $name,
                    'assigned_to' => 1,
                    'planned_start' => $baseDate->copy()->addDays(10 * $no)->toDateString(),
                    'planned_end' => $baseDate->copy()->addDays(10 * $no + 5 + $k)->toDateString(),
                    'actual_end' => $baseDate->copy()->addDays(10 * $no + 5 + $k)->toDateString(),
                    'progress_pct' => 100,
                    'status' => 'DONE',
                ]);
            }

            $phase->update(['status' => 'APPROVED', 'actual_end' => $baseDate->copy()->addDays(10 * $no + 6)->toDateString()]);

            $next = $project->phases()->where('phase_no', $no + 1)->first();
            $next->update(['status' => 'RUNNING', 'actual_start' => $baseDate->copy()->addDays(10 * $no + 7)->toDateString()]);
            $project->update(['current_phase_no' => $no + 1]);
        }

        // Fase berjalan: sebagian deliverable masih terbuka, satu task terlambat
        // — supaya penanda "perlu perhatian" di dashboard ada isinya.
        $running = $project->phases()->where('phase_no', $targetPhase)->first();
        $running->deliverables()->limit(1)->update(['status' => 'IN_PROGRESS', 'updated_at' => now()]);
        $running->tasks()->create([
            'name' => 'Menunggu drawing revisi dari pelanggan',
            'assigned_to' => 1,
            'planned_start' => $baseDate->copy()->addDays(10 * $targetPhase)->toDateString(),
            'planned_end' => now()->subDays(4)->toDateString(),
            'progress_pct' => 40,
            'status' => 'RUNNING',
        ]);

        $project->milestones()->createMany([
            ['name' => 'Design freeze', 'planned_date' => $baseDate->copy()->addMonths(2)->toDateString(), 'status' => 'PLANNED'],
            ['name' => 'PPAP submission', 'planned_date' => $baseDate->copy()->addMonths(4)->toDateString(), 'status' => 'PLANNED'],
            ['name' => 'Start of Production', 'planned_date' => $baseDate->copy()->addMonths(5)->toDateString(), 'status' => 'PLANNED'],
        ]);

        // Satu peran per orang per proyek — itu yang dijamin kunci uniknya.
        $project->members()->create(['user_id' => 1, 'role' => 'PM']);
    }
}
