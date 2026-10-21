<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AgeGroup;
use App\Models\PriceCoefficient;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\VisitorCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoefficientController extends Controller
{
    public function create(): View
    {
        return $this->form(new PriceCoefficient(['coefficient' => 1, 'is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $coefficient = PriceCoefficient::create($this->validated($request));
        ActivityLog::record('coefficient.created', $coefficient);

        return redirect()->route('admin.pricing.index')->with('success', __('Coefficient created.'));
    }

    public function edit(PriceCoefficient $coefficient): View
    {
        return $this->form($coefficient);
    }

    public function update(Request $request, PriceCoefficient $coefficient): RedirectResponse
    {
        $coefficient->update($this->validated($request));
        ActivityLog::record('coefficient.updated', $coefficient);

        return redirect()->route('admin.pricing.index')->with('success', __('Coefficient updated.'));
    }

    public function destroy(PriceCoefficient $coefficient): RedirectResponse
    {
        $coefficient->delete();
        ActivityLog::record('coefficient.deleted', $coefficient, ['name' => $coefficient->name]);

        return redirect()->route('admin.pricing.index')->with('success', __('Coefficient deleted.'));
    }

    protected function form(PriceCoefficient $coefficient): View
    {
        return view('admin.pricing.coefficient-form', [
            'coefficient' => $coefficient,
            'ageGroups' => AgeGroup::ordered()->get(),
            'timeSlots' => TimeSlot::ordered()->get(),
            'ticketTypes' => TicketType::ordered()->get(),
            'categories' => VisitorCategory::ordered()->get(),
        ]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'coefficient' => ['required', 'numeric', 'min:0', 'max:100'],
            'age_group_id' => ['nullable', 'exists:age_groups,id'],
            'time_slot_id' => ['nullable', 'exists:time_slots,id'],
            'ticket_type_id' => ['nullable', 'exists:ticket_types,id'],
            'visitor_category_id' => ['nullable', 'exists:visitor_categories,id'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $data['weekdays'] = array_values(array_map('intval', $data['weekdays'] ?? [])) ?: null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
