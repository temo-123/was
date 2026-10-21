<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AgeGroup;
use App\Models\PriceCoefficient;
use App\Models\PriceRule;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\VisitorCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The price matrix: rows = time slot × ticket type, columns = age group (× category).
 * Each cell is one price_rules row. Empty cell = not available.
 */
class PricingController extends Controller
{
    public function index(): View
    {
        $ageGroups = AgeGroup::ordered()->get();
        $categories = VisitorCategory::ordered()->get();

        // Column = [age group, category or null]. A group gets one column per category only
        // if it has category-specific prices (like "Adults: regular / member").
        $rules = PriceRule::all();
        $columns = $ageGroups->flatMap(function (AgeGroup $group) use ($rules, $categories) {
            $specific = $rules->where('age_group_id', $group->id)->whereNotNull('visitor_category_id')->pluck('visitor_category_id')->unique();

            return $specific->isEmpty()
                ? [['group' => $group, 'category' => null]]
                : $categories->whereIn('id', $specific)->map(fn ($c) => ['group' => $group, 'category' => $c])->values();
        });

        return view('admin.pricing.index', [
            'ageGroups' => $ageGroups,
            'timeSlots' => TimeSlot::ordered()->get(),
            'ticketTypes' => TicketType::ordered()->get(),
            'categories' => $categories,
            'columns' => $columns,
            'prices' => $rules->where('is_active', true)->keyBy(fn ($r) => "{$r->time_slot_id}-{$r->ticket_type_id}-{$r->age_group_id}-".($r->visitor_category_id ?? 0)),
            'coefficients' => PriceCoefficient::with('ageGroup', 'timeSlot', 'ticketType', 'category')->latest()->get(),
        ]);
    }

    /** Save the whole matrix. Input: prices[slot-ticket-group-category] = amount|''. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'prices' => ['array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['prices'] ?? [] as $key => $price) {
                [$slotId, $ticketId, $groupId, $categoryId] = array_map('intval', explode('-', $key)) + [0, 0, 0, 0];
                $attributes = [
                    'time_slot_id' => $slotId,
                    'ticket_type_id' => $ticketId,
                    'age_group_id' => $groupId,
                    'visitor_category_id' => $categoryId ?: null,
                ];

                if ($price === null || $price === '') {
                    PriceRule::where($attributes)->update(['is_active' => false]);
                } else {
                    PriceRule::updateOrCreate($attributes, ['price' => $price, 'is_active' => true]);
                }
            }
        });

        ActivityLog::record('pricing.updated', null, ['prices' => $data['prices'] ?? []]);

        return back()->with('success', __('Prices saved.'));
    }

    /** Add (or split) a price column: a category-specific column for an age group. */
    public function splitColumn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'age_group_id' => ['required', 'exists:age_groups,id'],
            'visitor_category_id' => ['required', 'exists:visitor_categories,id'],
        ]);

        // Copy the generic prices of the group as a starting point for the new category column.
        PriceRule::where('age_group_id', $data['age_group_id'])->whereNull('visitor_category_id')->get()
            ->each(fn (PriceRule $rule) => PriceRule::firstOrCreate([
                'age_group_id' => $rule->age_group_id,
                'time_slot_id' => $rule->time_slot_id,
                'ticket_type_id' => $rule->ticket_type_id,
                'visitor_category_id' => $data['visitor_category_id'],
            ], ['price' => $rule->price, 'is_active' => $rule->is_active]));

        // Guarantee at least one row so the column shows up.
        if (! PriceRule::where('age_group_id', $data['age_group_id'])->where('visitor_category_id', $data['visitor_category_id'])->exists()) {
            $slot = TimeSlot::ordered()->first();
            $ticket = TicketType::ordered()->first();
            if ($slot && $ticket) {
                PriceRule::create([
                    'age_group_id' => $data['age_group_id'], 'time_slot_id' => $slot->id, 'ticket_type_id' => $ticket->id,
                    'visitor_category_id' => $data['visitor_category_id'], 'price' => 0, 'is_active' => false,
                ]);
            }
        }

        return back()->with('success', __('Price column added.'));
    }

    // --- Dictionaries: age groups, time slots, ticket types, visitor categories ----------------

    public function settings(): View
    {
        return view('admin.pricing.settings', [
            'ageGroups' => AgeGroup::ordered()->get(),
            'timeSlots' => TimeSlot::ordered()->get(),
            'ticketTypes' => TicketType::ordered()->get(),
            'categories' => VisitorCategory::ordered()->get(),
        ]);
    }

    public function saveSetting(Request $request, string $type): RedirectResponse
    {
        [$model, $rules] = match ($type) {
            'age-groups' => [AgeGroup::class, [
                'min_age' => ['required', 'integer', 'min:0', 'max:120'],
                'max_age' => ['nullable', 'integer', 'gt:min_age', 'max:150'],
            ]],
            'time-slots' => [TimeSlot::class, [
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            ]],
            'ticket-types' => [TicketType::class, [
                'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
                'visits_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            ]],
            'categories' => [VisitorCategory::class, [
                'is_default' => ['boolean'],
            ]],
            default => abort(404),
        };

        $id = $request->integer('id') ?: null;
        $table = (new $model)->getTable();

        $data = $request->validate($rules + [
            'code' => ['required', 'alpha_dash', 'max:32', Rule::unique($table, 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'name_ka' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] ??= 0;

        if ($type === 'time-slots') {
            $data['start_time'] .= ':00';
            $data['end_time'] .= ':00';
        }

        $record = $id ? $model::findOrFail($id) : new $model;
        $record->fill($data)->save();

        if ($type === 'categories' && $request->boolean('is_default')) {
            VisitorCategory::whereKeyNot($record->id)->update(['is_default' => false]);
        }

        ActivityLog::record('pricing.'.$type.'.saved', $record);

        return back()->with('success', __('Saved.'));
    }
}
