<?php

namespace App\Http\Controllers;

use App\Models\AgeGroup;
use App\Models\PriceRule;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\VisitorCategory;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home', [
            'ageGroups' => AgeGroup::where('is_active', true)->ordered()->get(),
            'timeSlots' => TimeSlot::where('is_active', true)->ordered()->get(),
            'ticketTypes' => TicketType::where('is_active', true)->ordered()->get(),
            'categories' => VisitorCategory::where('is_active', true)->ordered()->get()->keyBy('id'),
            'prices' => PriceRule::where('is_active', true)->get(),
        ]);
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }
}
