<?php

namespace App\Modules\FormBuilder\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Facility;
use App\Modules\FormBuilder\Requests\FacilityAvailabilityRequest;
use App\Modules\FormBuilder\Requests\StoreFacilityRequest;
use App\Modules\FormBuilder\Requests\UpdateFacilityRequest;
use Illuminate\Support\Facades\DB;

class FacilityController extends Controller
{
    public function index()
    {
        $facilities = Facility::orderBy('name')->get();

        return inertia('facilities/FacilityListPage', [
            'facilities' => $facilities,
        ]);
    }

    public function availability(FacilityAvailabilityRequest $request)
    {
        $data = $request->validated();

        $query = DB::table('tbl_slots')
            ->where('date', $data['date'])
            ->where('status', '!=', 'Rejected');

        if (isset($data['facility_id'])) {
            $query->where('facility_id', (int) $data['facility_id']);
        } else {
            $query->whereNull('facility_id');
        }

        $slots = $query
            ->select(['id', 'date', 'start_time', 'end_time', 'facility_id', 'status'])
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'date' => $data['date'],
            'facility_id' => $data['facility_id'] ?? null,
            'slots' => $slots,
        ]);
    }

    public function store(StoreFacilityRequest $request)
    {
        $data = $request->validated();

        $facility = Facility::create($data + ['is_active' => true]);

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Facility created successfully');
    }

    public function update(UpdateFacilityRequest $request, $id)
    {
        $facility = Facility::findOrFail($id);
        $data = $request->validated();

        $facility->update($data);

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Facility updated successfully');
    }

    public function toggleStatus($id)
    {
        $facility = Facility::findOrFail($id);
        $facility->is_active = ! $facility->is_active;
        $facility->save();

        return redirect()->back()->with(
            'success',
            $facility->is_active ? 'Facility activated.' : 'Facility deactivated.'
        );
    }

    public function destroy($id)
    {
        $facility = Facility::findOrFail($id);

        // Check if facility has any bookings/slots
        $hasBookings = $facility->slots()->exists();

        if ($hasBookings) {
            return back()->withErrors([
                'message' => 'Cannot archive facility with existing bookings',
            ]);
        }

        $facility->delete();

        return back()->with('success', 'Facility archived successfully');
    }

    public function activeList()
    {
        $includeInactive = request()->boolean('include_inactive');

        $facilities = Facility::query()
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_active']);

        return response()->json($facilities);
    }
}
