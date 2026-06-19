<?php

namespace App\Modules\FormBuilder\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Facility;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\FormBuilder\Requests\IndexCalendarEventsRequest;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AdminCalendarController extends Controller
{
    /**
     * Calendar index page
     */
    public function index()
    {
        $facilities = Facility::where('is_active', true)->get();

        return inertia('facilities/FacilityDashboardPage', [
            'facilities' => $facilities,
        ]);
    }

    /**
     * Return events JSON for dashboard
     */
    public function events(IndexCalendarEventsRequest $request)
    {
        $data = $request->validated();

        $startDate = isset($data['start'])
            ? Carbon::createFromFormat('Y-m-d', $data['start'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $endDate = isset($data['end'])
            ? Carbon::createFromFormat('Y-m-d', $data['end'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        if ($startDate->diffInDays($endDate) > 93) {
            throw ValidationException::withMessages([
                'end' => 'The selected date window may not exceed 93 days.',
            ]);
        }

        $facilityId = $data['facility_id'] ?? null;
        $status = $data['status'] ?? null;

        $query = Slot::query()
            ->with(['facility', 'account.profile', 'form'])
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when($facilityId && $facilityId !== 'all', fn ($q) => $q->where('facility_id', $facilityId))
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status));

        $slots = $query->get();

        $events = $slots->map(function ($slot) {
            // Always have a date
            $dateOnly = $slot->date ? Carbon::parse($slot->date)->format('Y-m-d') : now()->format('Y-m-d');

            // Build start/end
            $start = $slot->start_time
                ? Carbon::parse("{$dateOnly} {$slot->start_time}")->toIso8601String()
                : Carbon::parse("{$dateOnly} 00:00:00")->toIso8601String();

            $end = $slot->end_time
                ? Carbon::parse("{$dateOnly} {$slot->end_time}")->toIso8601String()
                : Carbon::parse("{$dateOnly} 23:59:59")->toIso8601String();

            return [
                'id' => $slot->id,
                'facilityId' => $slot->facility_id,
                'facilityName' => $slot->facility?->name ?? 'No Facility',
                'formType' => $slot->form?->form_name ?? 'Form',
                'submissionId' => $slot->submission_id,
                'requester' => $slot->account?->profile?->full_name ?? 'Unknown User',
                'title' => $slot->title ?? $slot->form?->form_name ?? 'Untitled',
                'start' => $start,
                'end' => $end,
                'status' => $slot->status,
            ];
        });

        return response()->json($events->values());
    }

    /**
     * Upcoming approved events Inertia page
     */
    public function upcomingEvents()
    {
        $facilityId = request('facility_id');

        $facilities = Facility::orderBy('name')->get(['id', 'name']);

        $paginator = Slot::query()
            ->with(['facility', 'account.profile', 'form'])
            ->where('status', 'Approved')
            ->where('date', '>=', now()->toDateString())
            ->when(
                $facilityId && $facilityId !== 'all',
                fn ($q) => $q->where('facility_id', (int) $facilityId)
            )
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate(20)
            ->withQueryString();

        $events = $paginator->through(fn ($slot) => [
            'id' => $slot->id,
            'facilityId' => $slot->facility_id,
            'facilityName' => $slot->facility?->name ?? 'No Facility',
            'formType' => $slot->form?->form_name ?? 'Form',
            'submissionId' => $slot->submission_id,
            'requester' => $slot->account?->profile?->full_name ?? 'Unknown User',
            'title' => $slot->form?->form_name ?? 'Untitled',
            'date' => $slot->date?->format('Y-m-d'),
            'startTime' => $slot->start_time,
            'endTime' => $slot->end_time,
        ]);

        return inertia('facilities/FacilityUpcomingEventsPage', [
            'events' => $events,
            'facilities' => $facilities,
            'filters' => [
                'facility_id' => $facilityId,
            ],
        ]);
    }
}
