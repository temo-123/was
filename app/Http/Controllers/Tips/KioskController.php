<?php

namespace App\Http\Controllers\Tips;

use App\Http\Controllers\Controller;
use App\Models\TechTip;
use App\Services\Tips\TipMedia;
use App\Services\Tips\TipPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The tech tips page on the gym tablet (no login). Everything is loaded at once; the page switches language itself. */
class KioskController extends Controller
{
    /** Texts of resources/js/components/TechTips.vue, sent to the tablet in every language. */
    public const UI_TEXTS = [
        'Climbing tips', 'Tech tips', 'Choose a topic and learn something new', 'All topics', 'All levels', 'Back', 'Back to all tips',
        'Previous', 'Next', 'Tips will appear here soon.', 'No tips on this topic yet.', 'Text size', 'Full screen',
        'Touch the screen to explore climbing tips', 'For everyone', 'min read', 'More on this topic',
        'Correct', 'Wrong', 'More info', 'Safety first',
        'These tips do not replace training with an instructor. If you are not sure, ask our staff.',
        'Preview — not saved yet', 'Start the tip', 'slides', "What's inside", 'Finish', 'Well done!', 'Start again',
        'You have finished this tip.', 'Close',
    ];

    public function __construct(private TipPresenter $presenter) {}

    public function index(): View
    {
        return view('tips.kiosk', ['props' => self::props(TechTip::published()->ordered()->with('articles', 'slides.texts')->get()->map(fn (TechTip $tip) => $this->presenter->present($tip))->all())]);
    }

    /** Everything the tablet page needs besides the tips (the admin preview uses the same page). */
    public static function props(array $tips, array $extra = []): array
    {
        $locales = config('app.locales');

        return $extra + [
            'tips' => $tips,
            'categories' => collect(config('tips.categories'))->map(fn ($c, $key) => [
                'key' => $key,
                'icon' => $c['icon'],
                'color' => $c['color'] ?? '#ff7a1a',
                'label' => collect($locales)->map(fn ($name, $locale) => __($c['label'], [], $locale)),
            ])->values(),
            'levels' => collect(config('tips.levels'))->map(fn ($label) => collect($locales)->map(fn ($name, $locale) => __($label, [], $locale))),
            'texts' => collect($locales)->map(fn ($name, $locale) => collect(self::UI_TEXTS)->mapWithKeys(fn ($text) => [$text => __($text, [], $locale)])),
            'locales' => $locales,
            'locale' => app()->getLocale(),
            'gym' => config('app.name'),
            'idleSeconds' => config('tips.idle_seconds'),
            'refreshSeconds' => config('tips.refresh_seconds'),
            'dataUrl' => route('tips.data'),
            'viewedUrl' => route('tips.viewed', ['tip' => '__ID__']),
        ];
    }

    /** Polled by the tablet: new / edited / hidden tips appear without reloading the page. */
    public function data(): JsonResponse
    {
        return response()->json(['tips' => TechTip::published()->ordered()->with('articles', 'slides.texts')->get()->map(fn (TechTip $tip) => $this->presenter->present($tip))->all()]);
    }

    public function viewed(TechTip $tip): Response
    {
        abort_unless($tip->is_published, 404);
        $tip->timestamps = false;
        $tip->increment('views');

        return response()->noContent();
    }

    /**
     * Pictures and videos are kept outside public/, so they work without `storage:link` (also on XAMPP).
     * A file never changes (new upload = new name), so the tablet may keep it for good. A file response
     * also answers "Range" requests, which the tablet needs to jump around in a video.
     */
    public function media(string $file): BinaryFileResponse
    {
        $path = TipMedia::DIR.'/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
