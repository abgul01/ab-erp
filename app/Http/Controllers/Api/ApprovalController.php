<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Support\ApiResponse;
use App\Support\ApprovalEngine;
use Illuminate\Http\Request;

/**
 * Multi-level approval hub (LLD §4.2).
 * Lists all documents pending the current user's approval action.
 */
class ApprovalController extends Controller
{
    /**
     * My pending approvals across all document types.
     */
    public function myPending(Request $request)
    {
        $engine = app(ApprovalEngine::class);
        $items = $engine->pendingFor($request->user());

        return ApiResponse::collection($items);
    }

    /**
     * Approval history for a specific document.
     */
    public function history(Request $request, string $docType, int $docId)
    {
        $rows = Approval::with('actor')
            ->where('doc_type', $docType)
            ->where('doc_id', $docId)
            ->orderBy('level')
            ->get();

        return ApiResponse::collection($rows);
    }

    /**
     * Approve the level this row represents. The engine checks that it is the
     * outstanding one, so approving out of order is not possible.
     */
    public function approve(Request $request, int $id)
    {
        $data = $request->validate(['note' => 'nullable|string|max:300']);
        $approval = Approval::findOrFail($id);

        $engine = app(ApprovalEngine::class);
        $doc = $engine->resolveDoc($approval->doc_type, $approval->doc_id);
        if (! $doc) {
            return ApiResponse::notFound('Dokumen untuk approval ini sudah tidak ada.');
        }

        $complete = $engine->approve($doc, $request->user(), $data['note'] ?? null);

        return ApiResponse::item([
            'fully_approved' => $complete,
            'message' => $complete
                ? 'Dokumen disetujui sepenuhnya.'
                : 'Level ini disetujui. Menunggu level berikutnya.',
        ]);
    }

    /** Put a document into the approval flow. */
    public function submit(Request $request, string $docType, int $docId)
    {
        $engine = app(ApprovalEngine::class);
        $doc = $engine->resolveDoc($docType, $docId);
        if (! $doc) {
            return ApiResponse::notFound("Tipe dokumen '{$docType}' tidak bisa diajukan approval.");
        }

        $engine->submit($doc);

        return ApiResponse::item(['message' => 'Dokumen diajukan untuk persetujuan.']);
    }

    /** Document types that can be routed for approval, for the UI's picker. */
    public function types()
    {
        $engine = app(ApprovalEngine::class);

        return ApiResponse::collection(
            collect($engine->registry())->map(fn ($d, $k) => [
                'doc_type' => $k,
                'label' => $d['label'],
                'levels' => $d['levels'],
            ])->values()
        );
    }

    /**
     * Reject with note.
     */
    public function reject(Request $request, int $id)
    {
        $data = $request->validate(['note' => 'required|string|max:300']);
        $approval = Approval::findOrFail($id);

        $engine = app(ApprovalEngine::class);
        $doc = $engine->resolveDoc($approval->doc_type, $approval->doc_id);
        if (! $doc) {
            return response()->json(['errors' => [['code' => 'DOC_NOT_FOUND']]], 404);
        }

        $engine->reject($doc, $request->user(), $data['note']);

        return ApiResponse::item(['message' => 'Dokumen berhasil di-reject.']);
    }
}
