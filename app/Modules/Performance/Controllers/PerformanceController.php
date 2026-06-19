<?php

namespace App\Modules\Performance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Performance\Requests\PerformanceFilterRequest;
use App\Modules\Performance\Services\StaffPerformanceQueryService;
use Inertia\Inertia;

class PerformanceController extends Controller
{
    /**
     * Display the Performance module page.
     *
     * @return \Inertia\Response
     */
    public function index(PerformanceFilterRequest $request, StaffPerformanceQueryService $performanceService)
    {
        $filters = $request->validated();

        $metrics = $performanceService->getPerformanceReport($filters);
        $pending = $performanceService->getCurrentlyPending($filters);

        return Inertia::render('Performance/Index', [
            'metrics' => $metrics,
            'pending' => $pending,
            'filters' => $filters,
        ]);
    }

    /**
     * Get staff performance data as JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(PerformanceFilterRequest $request, StaffPerformanceQueryService $performanceService)
    {
        $filters = $request->validated();

        return response()->json([
            'metrics' => $performanceService->getPerformanceReport($filters),
            'pending' => $performanceService->getCurrentlyPending($filters),
        ]);
    }
}
