<?php

namespace App\Services;

use App\Models\Visitor;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Data for the month calendar of a visitor's visits (admin visitor page, the climber's profile). */
class VisitCalendar
{
    /** ?month=2026-10, else the month of ?day, else the month of the last visit, else this month. Bad values are ignored. */
    public function month(Request $request, ?string $lastVisit = null): Carbon
    {
        foreach ([['month', '!Y-m'], ['day', '!Y-m-d']] as [$param, $format]) {
            $value = $request->query($param);
            try {
                $date = is_string($value) ? Carbon::createFromFormat($format, $value) : null;
            } catch (\Throwable) {
                $date = null;
            }
            if ($date && $date->format(substr($format, 1)) === $value && $date->year >= 2000 && $date->year <= 2100) {
                return $date->startOfMonth();
            }
        }

        return ($lastVisit ? Carbon::parse($lastVisit) : today())->startOfMonth();
    }

    /** The selected day (?day=2026-10-14) as "Y-m-d", or null. */
    public function day(Request $request): ?string
    {
        $value = $request->query('day');

        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : null;
    }

    /**
     * @return array{items: Collection, yearCounts: array<int, int>, monthVisits: int, monthSpent: float}
     */
    public function forVisitor(Visitor $visitor, CarbonInterface $month): array
    {
        // The whole grid (it starts on the Monday before the 1st and ends on the Sunday after the last day).
        $from = $month->copy()->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $to = $month->copy()->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $visits = $visitor->visits()
            ->with('ticketType', 'timeSlot', 'rentals', 'tier', 'creator')
            ->whereBetween('entered_at', [$from, $to])
            ->orderBy('entered_at')
            ->get();
        $inMonth = $visits->filter(fn ($v) => $v->entered_at->isSameMonth($month));

        return [
            'items' => $visits->groupBy(fn ($v) => $v->entered_at->toDateString()),
            'yearCounts' => $visitor->visits()
                ->whereYear('entered_at', $month->year)
                ->selectRaw('MONTH(entered_at) as m, COUNT(*) as n')
                ->groupBy('m')
                ->pluck('n', 'm')
                ->map(fn ($n) => (int) $n)
                ->all(),
            'monthVisits' => $inMonth->count(),
            'monthSpent' => (float) $inMonth->sum('total_price'),
        ];
    }
}
