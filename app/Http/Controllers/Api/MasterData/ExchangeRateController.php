<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\m_rate;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\KursService;
use Illuminate\Http\Request;

/**
 * Exchange rates, including the weekly KMK rate used for import taxes.
 *
 * PRD §4.2, §6
 */
class ExchangeRateController extends Controller
{
    public function __construct(private KursService $kurs) {}

    public function index(Request $request)
    {
        $q = m_rate::with('currency')->orderByDesc('valid_date')->orderByDesc('id');

        if ($type = $request->query('rate_type')) {
            $q->where('rate_type', $type);
        }
        if ($cur = $request->query('currency_id')) {
            $q->where('currency_id', $cur);
        }

        return ApiResponse::paginated($q->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(m_rate::with('currency')->findOrFail($id));
    }

    /** The rate a document dated $date must use. */
    public function effective(Request $request)
    {
        $data = $request->validate([
            'currency_id' => ['required', 'integer', 'exists:m_currency,id'],
            'date' => ['required', 'date'],
            'rate_type' => ['nullable', 'string', 'max:10'],
        ]);

        $type = $data['rate_type'] ?? KursService::KMK;
        $rate = $this->kurs->rateOn((int) $data['currency_id'], $data['date'], $type);

        return ApiResponse::item([
            'currency_id' => (int) $data['currency_id'],
            'date' => $data['date'],
            'rate_type' => $type,
            'rate' => $rate,
            'found' => $rate !== null,
        ]);
    }

    public function store(Request $request)
    {
        $row = m_rate::create($this->validated($request));
        AuditLogger::record($request, "Kurs {$row->rate_type} {$row->valid_date?->toDateString()} = {$row->rate}");

        return ApiResponse::item($row->load('currency'), 201);
    }

    public function update(Request $request, int $id)
    {
        $row = m_rate::findOrFail($id);
        $row->update($this->validated($request));
        AuditLogger::record($request, "Ubah kurs #{$id} = {$row->rate}");

        return ApiResponse::item($row->fresh()->load('currency'));
    }

    public function destroy(Request $request, int $id)
    {
        m_rate::findOrFail($id)->delete();
        AuditLogger::record($request, "Hapus kurs #{$id}");

        return ApiResponse::empty();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'currency_id' => ['required', 'integer', 'exists:m_currency,id'],
            'rate_type' => ['required', 'string', 'max:10'],
            'valid_date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
