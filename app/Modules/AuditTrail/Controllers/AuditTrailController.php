<?php

namespace App\Modules\AuditTrail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AuditTrail\Models\AuditLog;
use App\Modules\AuditTrail\Resources\AuditLogResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditTrailController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    public function index()
    {
        return Inertia::render('audit-trail/AuditTrailPage');
    }

    public function data(Request $req)
    {
        $validated = $req->validate([
            'category' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        return AuditLogResource::collection(
            $this->buildFilteredQuery($req)->paginate($perPage)->withQueryString()
        );
    }

    public function export(Request $req)
    {
        $req->validate([
            'category' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $filename = 'audit_logs_'.now()->format('Ymd_His').'.csv';
        $query = $this->buildFilteredQuery($req);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Category', 'Action', 'Status', 'Actor', 'IP', 'Auditable', 'Snapshot', 'QR', 'Description']);
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->created_at,
                        $r->category,
                        $r->action,
                        $r->status,
                        $r->actor_name,
                        $r->ip_address,
                        $r->auditable_type.'#'.$r->auditable_id,
                        $r->snapshot_public_id ?? $r->snapshot_id,
                        $r->verification_result.' | '.$r->qr_payload,
                        $r->description,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function buildFilteredQuery(Request $req): Builder
    {
        return AuditLog::query()
            ->when($req->filled('category') && $req->string('category')->toString() !== 'all', fn (Builder $query) => $query->where('category', $req->string('category')->toString()))
            ->when($req->filled('status'), fn (Builder $query) => $query->where('status', $req->string('status')->toString()))
            ->when($req->filled('search'), function (Builder $query) use ($req): void {
                $search = $req->string('search')->toString();
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('description', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('snapshot_public_id', 'like', "%{$search}%")
                        ->orWhere('qr_payload', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');
    }
}
