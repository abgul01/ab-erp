<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\eng_ecn_main;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Engineering Change Notice: agree the change, then make it.
 *
 * Three rules do the real work here.
 *
 * Only listed fields can be changed. An ECN writes straight into master tables,
 * so the set of columns it may touch is declared rather than taken from the
 * request — otherwise a crafted payload could rewrite an id or a status.
 *
 * The value a notice was approved against must still be there when it is
 * applied. If someone edited the item in the meantime, applying the notice
 * would silently overwrite their change with a figure the approver never saw;
 * that is refused and reported field by field.
 *
 * Applying bumps the revision of what changed. "Which revision was this lot
 * built to" is the question an ECN exists to answer.
 *
 * PRD §4.3
 */
class EcnService
{
    /** Columns an ECN may write, per table. Everything else is refused. */
    public const ALLOWED = [
        'm_item' => [
            'part_name', 'descrip', 'o_d', 'i_d', 'thick', 'width', 'height',
            'length', 'length_cut', 'weight', 'tolerance',
            'min_stock', 'max_stock', 'moq', 'order_lot', 'lead_time_days', 'active',
        ],
        'm_bom_det_rm' => ['mat_id', 'length_cut', 'length_use', 'priority'],
        'm_bom_det_pm' => ['pm_id', 'qty'],
        'm_process_main_det' => ['proc_id', 'sequence'],
    ];

    /** Which table each kind of notice is allowed to touch. */
    public const SCOPE = [
        'ITEM' => ['m_item'],
        'BOM' => ['m_bom_det_rm', 'm_bom_det_pm'],
        'ROUTING' => ['m_process_main_det'],
    ];

    /** The master whose revision a notice of each kind bumps. */
    private const REV_TABLE = ['ITEM' => 'm_item', 'BOM' => 'm_bom', 'ROUTING' => 'm_process_main'];

    public function __construct(private BomToolService $bom) {}

    /**
     * Validate a line against the whitelist. Called before anything is stored,
     * so an impossible notice never reaches an approver's queue.
     */
    public function assertLineAllowed(string $changeType, array $line): void
    {
        $table = $line['target_table'];
        $action = $line['action'] ?? 'UPDATE';

        if (! in_array($table, self::SCOPE[$changeType] ?? [], true)) {
            throw BizException::make('ECN_SCOPE', "ECN bertipe {$changeType} tidak boleh mengubah tabel {$table}.");
        }

        // A removal takes the whole row; only the other two name a field.
        if ($action !== 'REMOVE' && ! in_array($line['field'] ?? '', self::ALLOWED[$table] ?? [], true)) {
            throw BizException::make('ECN_FIELD', "Kolom {$line['field']} pada {$table} tidak dapat diubah lewat ECN.");
        }

        if ($action !== 'ADD' && empty($line['target_id'])) {
            throw BizException::make('ECN_TARGET', 'Baris yang diubah atau dihapus harus menunjuk data yang ada.');
        }
    }

    /**
     * Record what each field holds right now.
     *
     * This is the "before" an approver reads and the value applying is checked
     * against later, so it is taken from the database rather than trusted from
     * the browser.
     */
    public function snapshot(eng_ecn_main $ecn): void
    {
        foreach ($ecn->detail as $line) {
            if ($line->action === 'ADD' || ! $line->target_id) {
                continue;
            }

            $current = DB::table($line->target_table)->where('id', $line->target_id)->first();
            if (! $current) {
                throw BizException::make('ECN_TARGET_GONE', "Data {$line->target_table}#{$line->target_id} sudah tidak ada.");
            }

            $line->update([
                'old_value' => $line->action === 'REMOVE'
                    ? json_encode($this->rowFields($line->target_table, $current))
                    : (string) ($current->{$line->field} ?? ''),
            ]);
        }
    }

    /**
     * What this change touches: which products use the part, which BOMs carry
     * it, and which work orders are already running against it.
     *
     * An approver needs this before agreeing — a dimension change is cheap on
     * paper and expensive when three work orders are half-built to the old one.
     */
    public function impact(int $itemId): array
    {
        $openWo = DB::table('prd_wo_main as w')
            ->join('m_item as i', 'i.id', '=', 'w.fg_id')
            ->where(fn ($q) => $q->where('w.fg_id', $itemId)
                ->orWhereIn('w.id', DB::table('prd_wo_detail_rm')->where('rm_id', $itemId)->select('main_id')))
            ->whereIn('w.status', [1, 2])          // draft & released
            ->select('w.id', 'w.code', 'w.qty', 'w.status', 'i.code as fg_code')
            ->limit(50)->get();

        $bomId = DB::table('m_bom')->where('item_id', $itemId)->where('active', 1)->value('id');
        $routingId = DB::table('m_bom_pro')->where('item_id', $itemId)->orderBy('priority')->value('process_main_id');

        return [
            'where_used' => $this->bom->whereUsed($itemId),
            'open_wo' => $openWo,
            'open_wo_count' => $openWo->count(),
            'active_bom' => $bomId ? DB::table('m_bom')->where('id', $bomId)->first() : null,
            'routings' => DB::table('m_bom_pro as p')
                ->join('m_process_main as m', 'm.id', '=', 'p.process_main_id')
                ->where('p.item_id', $itemId)
                ->select('m.id', 'm.code', 'm.name', 'm.rev', 'p.priority')
                ->get(),
            /*
             * The actual rows a notice can point at. Without these the screen
             * would be asking an engineer to type a row id, which is how the
             * wrong line gets changed.
             */
            'bom_lines' => $bomId ? DB::table('m_bom_det_rm as d')
                ->join('m_item as i', 'i.id', '=', 'd.mat_id')
                ->where('d.id_prim', $bomId)
                ->select('d.id', 'd.mat_id', 'd.length_cut', 'd.length_use', 'd.priority', 'i.code as mat_code', 'i.part_name')
                ->orderBy('d.priority')->get() : collect(),
            'routing_steps' => $routingId ? DB::table('m_process_main_det as d')
                ->join('m_process as p', 'p.id', '=', 'd.proc_id')
                ->where('d.main_id', $routingId)
                ->select('d.id', 'd.proc_id', 'd.sequence', 'p.name_p as proc_name')
                ->orderBy('d.sequence')->get() : collect(),
        ];
    }

    /**
     * Make the change.
     *
     * Refused unless the notice is approved and its effective date has arrived:
     * an ECN is a dated instruction, and applying it early is exactly the error
     * it exists to prevent.
     */
    public function apply(eng_ecn_main $ecn, int $userId): eng_ecn_main
    {
        if ($ecn->status !== 'APPROVED') {
            throw BizException::make('ECN_NOT_APPROVED', 'Hanya ECN yang sudah disetujui yang dapat diterapkan.');
        }

        if ($ecn->effective_date->isAfter(now()->startOfDay())) {
            throw BizException::make(
                'ECN_NOT_EFFECTIVE',
                "ECN berlaku mulai {$ecn->effective_date->toDateString()} — belum dapat diterapkan hari ini."
            );
        }

        $ecn->load('detail');
        $this->assertNoDrift($ecn);

        return DB::transaction(function () use ($ecn, $userId) {
            foreach ($ecn->detail as $line) {
                match ($line->action) {
                    'ADD' => DB::table($line->target_table)->insert($this->addPayload($ecn, $line)),
                    'REMOVE' => DB::table($line->target_table)->where('id', $line->target_id)->delete(),
                    default => DB::table($line->target_table)
                        ->where('id', $line->target_id)
                        ->update([$line->field => $line->new_value]),
                };
            }

            $this->bumpRevision($ecn);

            $ecn->update([
                'status' => 'APPLIED',
                'applied_by' => $userId,
                'applied_at' => now(),
            ]);

            return $ecn->fresh()->load('detail');
        });
    }

    /**
     * Has the master moved since the approver read it?
     *
     * Applying over someone else's edit would replace a value nobody reviewed
     * with one nobody knows is stale, and leave no sign either happened.
     */
    private function assertNoDrift(eng_ecn_main $ecn): void
    {
        $drifted = [];

        foreach ($ecn->detail as $line) {
            if ($line->action === 'ADD' || ! $line->target_id) {
                continue;
            }

            $current = DB::table($line->target_table)->where('id', $line->target_id)->first();

            if (! $current) {
                $drifted[] = "{$line->target_table}#{$line->target_id} sudah dihapus";

                continue;
            }

            if ($line->action === 'REMOVE') {
                continue;
            }

            $now = (string) ($current->{$line->field} ?? '');
            if ($this->differs($now, (string) $line->old_value)) {
                $drifted[] = "{$line->target_table}.{$line->field}: saat ECN dibuat '{$line->old_value}', sekarang '{$now}'";
            }
        }

        if ($drifted) {
            throw BizException::make(
                'ECN_DRIFT',
                'Data master sudah berubah sejak ECN disetujui, jadi ECN tidak diterapkan: '
                .implode('; ', $drifted).'. Buat ECN baru atas nilai yang berlaku sekarang.'
            );
        }
    }

    /**
     * Numbers stored as 25 and 25.00 are the same value wearing different
     * clothes; only a real difference should block an ECN.
     */
    private function differs(string $a, string $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) > 0.00001;
        }

        return $a !== $b;
    }

    /**
     * A new row belongs to the item the notice is about, so its parent is
     * derived here rather than taken from the request — an ECN on one part must
     * not be able to add a line to another part's BOM.
     */
    private function addPayload(eng_ecn_main $ecn, Model $line): array
    {
        $values = json_decode((string) $line->new_value, true);

        if (! is_array($values)) {
            throw BizException::make('ECN_ADD_PAYLOAD', 'Baris tambahan harus berisi data JSON kolom yang diisi.');
        }

        $allowed = array_intersect_key($values, array_flip(self::ALLOWED[$line->target_table] ?? []));

        $parent = match ($line->target_table) {
            'm_bom_det_rm', 'm_bom_det_pm' => ['id_prim' => $this->bomIdFor($ecn->item_id)],
            'm_process_main_det' => ['main_id' => $this->routingIdFor($ecn->item_id)],
            default => throw BizException::make('ECN_ADD_SCOPE', "Tidak bisa menambah baris pada {$line->target_table}."),
        };

        return $allowed + $parent + ['created_at' => now(), 'updated_at' => now()];
    }

    private function bomIdFor(int $itemId): int
    {
        $id = DB::table('m_bom')->where('item_id', $itemId)->where('active', 1)->value('id');

        if (! $id) {
            throw BizException::make('ECN_NO_BOM', 'Item ini belum punya BOM aktif untuk ditambahi baris.');
        }

        return (int) $id;
    }

    private function routingIdFor(int $itemId): int
    {
        $id = DB::table('m_bom_pro')->where('item_id', $itemId)->orderBy('priority')->value('process_main_id');

        if (! $id) {
            throw BizException::make('ECN_NO_ROUTING', 'Item ini belum punya routing untuk ditambahi proses.');
        }

        return (int) $id;
    }

    /** The columns worth keeping when a whole row is removed. */
    private function rowFields(string $table, object $row): array
    {
        return array_intersect_key((array) $row, array_flip(self::ALLOWED[$table] ?? []));
    }

    private function bumpRevision(eng_ecn_main $ecn): void
    {
        $table = self::REV_TABLE[$ecn->change_type];

        $id = match ($ecn->change_type) {
            'ITEM' => $ecn->item_id,
            'BOM' => DB::table('m_bom')->where('item_id', $ecn->item_id)->where('active', 1)->value('id'),
            'ROUTING' => DB::table('m_bom_pro')->where('item_id', $ecn->item_id)->orderBy('priority')->value('process_main_id'),
        };

        if (! $id) {
            return;
        }

        DB::table($table)->where('id', $id)->update([
            'rev' => DB::raw('rev + 1'),
            'rev_date' => $ecn->effective_date->toDateString(),
        ]);
    }
}
