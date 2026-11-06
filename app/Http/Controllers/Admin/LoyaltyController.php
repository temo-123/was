<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ClientTier;
use App\Models\RentalItem;
use App\Models\VisitReward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Client statuses with a discount (VIP −50 %, Gold −100 %…) and rewards for every N-th visit. */
class LoyaltyController extends Controller
{
    public function index(): View
    {
        return view('admin.loyalty.index', [
            'tiers' => ClientTier::ordered()->withCount(['visitors' => fn ($q) => $q->where(fn ($w) => $w->whereNull('client_tier_until')->orWhereDate('client_tier_until', '>=', today()))])->get(),
            'rewards' => VisitReward::ordered()->with('rentalItem')->get(),
            'rentalItems' => RentalItem::ordered()->get(),
            'types' => VisitReward::TYPES,
        ]);
    }

    public function saveTier(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:client_tiers,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_ka' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'rental_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);
        $data['rental_discount_percent'] ??= 0;
        $data['sort_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        $tier = ClientTier::updateOrCreate(['id' => $data['id'] ?? null], collect($data)->except('id')->all());
        ActivityLog::record($tier->wasRecentlyCreated ? 'client_tier.created' : 'client_tier.updated', $tier, ['discount' => $tier->discount_percent]);

        return back()->with('success', __(':name saved.', ['name' => $tier->local_name]));
    }

    public function saveReward(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:visit_rewards,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_ka' => ['nullable', 'string', 'max:255'],
            'every_visits' => ['required', 'integer', 'min:2', 'max:10000'],
            'type' => ['required', Rule::in(array_keys(VisitReward::TYPES))],
            'value' => ['nullable', 'required_if:type,discount', 'numeric', 'min:1', 'max:100'],
            'rental_item_id' => ['nullable', 'required_if:type,free_rental', 'exists:rental_items,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);
        $data['value'] = $data['type'] === 'discount' ? $data['value'] : null;
        $data['rental_item_id'] = $data['type'] === 'free_rental' ? $data['rental_item_id'] : null;
        $data['sort_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        $reward = VisitReward::updateOrCreate(['id' => $data['id'] ?? null], collect($data)->except('id')->all());
        ActivityLog::record($reward->wasRecentlyCreated ? 'visit_reward.created' : 'visit_reward.updated', $reward, ['every' => $reward->every_visits]);

        return back()->with('success', __(':name saved.', ['name' => $reward->local_name]));
    }
}
