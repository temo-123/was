<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tips\KioskController;
use App\Http\Requests\TechTipRequest;
use App\Models\ActivityLog;
use App\Models\TechTip;
use App\Models\TechTipArticle;
use App\Models\TechTipSlide;
use App\Services\Tips\TabletUrl;
use App\Services\Tips\TipMedia;
use App\Services\Tips\TipPresenter;
use App\Services\Tips\TipSlides;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CMS for the tech tips shown on the gym tablet (KioskController). Start screen + slides are edited with TipEditor.vue. */
class TechTipController extends Controller
{
    public function index(Request $request, TabletUrl $tablet): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(array_keys(config('tips.categories')))],
            'status' => ['nullable', Rule::in(['published', 'hidden'])],
        ]);

        $tips = TechTip::with('articles')->withCount('slides')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->whereHas('articles', fn ($a) => $a->where('title', 'like', $like)->orWhere('short_description', 'like', $like));
            })
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_published', $status === 'published'))
            ->ordered()
            ->paginate(30)
            ->withQueryString();

        $url = $tablet->url($request);

        return view('admin.tech-tips.index', [
            'tips' => $tips,
            'filtered' => array_filter($filters) !== [],
            'counts' => TechTip::selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category'),
            'tabletUrl' => $url,
            'tabletSvg' => $tablet->svg($url),
        ]);
    }

    public function create(): View
    {
        return $this->form(new TechTip([
            'is_published' => true,
            'show_warning' => true,
            'sort_order' => (TechTip::max('sort_order') ?? 0) + 10,
        ]));
    }

    public function store(TechTipRequest $request, TipMedia $media): RedirectResponse
    {
        $tip = DB::transaction(function () use ($request) {
            $tip = TechTip::create($this->tipData($request) + ['created_by' => $request->user()->id]);
            $this->saveContent($tip, $request);

            return $tip;
        });
        $media->sweep();
        ActivityLog::record('tech_tip.created', $tip, ['title' => $tip->load('articles')->article()?->title]);

        return $this->saved($request, $tip);
    }

    public function edit(TechTip $tip): View
    {
        return $this->form($tip->load('articles', 'slides.texts'));
    }

    public function update(TechTipRequest $request, TechTip $tip, TipMedia $media, TipSlides $slides): RedirectResponse
    {
        $before = $this->files($tip, $slides);

        DB::transaction(function () use ($request, $tip) {
            $tip->update($this->tipData($request));
            $this->saveContent($tip, $request);
            $tip->touch(); // the tablet notices the change even when only a text was edited
        });
        $media->sweep($before);
        ActivityLog::record('tech_tip.updated', $tip, ['title' => $tip->load('articles')->article()?->title]);

        return $this->saved($request, $tip);
    }

    public function destroy(TechTip $tip, TipMedia $media, TipSlides $slides): RedirectResponse
    {
        $title = $tip->article()?->title;
        $files = $this->files($tip, $slides);
        $tip->delete();
        $media->sweep($files);
        ActivityLog::record('tech_tip.deleted', $tip, ['title' => $title]);

        return redirect()->route('admin.tech-tips.index')->with('success', __('Tip deleted.'));
    }

    /** Show on the tablet / hide, from the list. */
    public function toggle(TechTip $tip): RedirectResponse
    {
        $tip->update(['is_published' => ! $tip->is_published]);
        ActivityLog::record($tip->is_published ? 'tech_tip.published' : 'tech_tip.hidden', $tip);

        return back()->with('success', $tip->is_published ? __('The tip is shown on the tablet.') : __('The tip is hidden from the tablet.'));
    }

    /** The editor uploads every picture / video right away and keeps only its name. */
    public function upload(Request $request, TipMedia $media): JsonResponse
    {
        $video = $request->input('kind') === 'video';
        $request->validate(['file' => $video
            ? ['required', 'file', 'mimes:mp4,webm', 'max:'.(config('tips.video_max_mb') * 1024)]
            : ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:12288'],
        ]);

        $path = $media->store($request->file('file'));

        return response()->json(['src' => basename($path), 'url' => $media->url($path)]);
    }

    /** "Preview on the tablet": the form as it is now (not saved), on the real tablet page. */
    public function preview(TechTipRequest $request, TipPresenter $presenter): View
    {
        $id = (int) $request->query('tip', 0);
        $tip = new TechTip($this->tipData($request));
        $tip->id = $id;
        $tip->setRelation('articles', collect($request->articles())
            ->filter(fn ($a) => filled($a['title']))
            ->map(fn ($a, $locale) => new TechTipArticle(['locale' => $locale] + $a))
            ->values());

        // The other tips too, so "more info" links and "more on this topic" work.
        $others = TechTip::published()->ordered()->with('articles', 'slides.texts')->whereKeyNot($id)->get()
            ->map(fn (TechTip $other) => $presenter->present($other));

        return view('tips.kiosk', ['props' => KioskController::props([$presenter->present($tip, $request->slides()), ...$others], [
            'preview' => true,
            'open' => $id,
            'openSlide' => max(0, (int) $request->input('preview_slide', 0)),
        ])]);
    }

    private function form(TechTip $tip): View
    {
        $old = session()->hasOldInput();
        $slides = app(TipSlides::class);

        return view('admin.tech-tips.form', [
            'tip' => $tip,
            'editor' => [
                'articles' => collect(config('app.locales'))->map(function ($language, $locale) use ($tip, $old) {
                    $article = $tip->articles->firstWhere('locale', $locale);

                    return [
                        'title' => (string) ($old ? old("articles.$locale.title") : $article?->title),
                        'short' => (string) ($old ? old("articles.$locale.short_description") : $article?->short_description),
                    ];
                }),
                'image' => $old ? old('image') : ($tip->image ? basename($tip->image) : null),
                'slides' => $old
                    ? (json_decode((string) old('slides'), true) ?: [])
                    : $tip->slides->map(fn (TechTipSlide $s) => $slides->fromModel($s))->all(),
                'errors' => collect(session('errors')?->getBag('default')->toArray() ?? [])->map(fn ($messages) => $messages[0]),
                'locales' => config('app.locales'),
                'uploadUrl' => route('admin.tech-tips.upload'),
                'mediaUrl' => route('tips.media', ['file' => '__FILE__']),
                'videoMaxMb' => config('tips.video_max_mb'),
                // For "more info" links to other tips.
                'tips' => TechTip::with('articles')->ordered()->whereKeyNot($tip->id ?? 0)->get()->map(fn (TechTip $t) => [
                    'id' => $t->id,
                    'title' => $t->article()?->title,
                    'published' => $t->is_published,
                ])->values(),
            ],
        ]);
    }

    private function tipData(TechTipRequest $request): array
    {
        return [
            'category' => $request->validated('category'),
            'level' => $request->validated('level'),
            'sort_order' => (int) $request->validated('sort_order', 0),
            'is_published' => $request->boolean('is_published'),
            'show_warning' => $request->boolean('show_warning'),
            'image' => $request->cover(),
        ];
    }

    /** Start screen per language (a language whose title was cleared is removed) and the slides (saved anew each time). */
    private function saveContent(TechTip $tip, TechTipRequest $request): void
    {
        foreach ($request->articles() as $locale => $data) {
            $article = $tip->articles()->firstWhere('locale', $locale);
            if (blank($data['title'])) {
                $article?->delete();
            } else {
                ($article ?? new TechTipArticle(['locale' => $locale]))->fill($data)->tip()->associate($tip)->save();
            }
        }

        $tip->slides()->delete();
        foreach ($request->slides() as $position => $slide) {
            $model = $tip->slides()->create([
                'position' => $position,
                'layout' => $slide['layout'],
                'media' => $slide['media'],
                'light' => $slide['light'],
                'note_variant' => $slide['note'],
                'link_tip_id' => $slide['link'] && $slide['link'] !== $tip->id && TechTip::whereKey($slide['link'])->exists() ? $slide['link'] : null,
            ]);
            foreach ($slide['texts'] as $locale => $text) {
                $model->texts()->create([
                    'locale' => $locale,
                    'title' => $text['title'] ?: null,
                    'body' => $text['body'] ?: null,
                    'note' => $text['note'] ?: null,
                    'captions' => (object) $text['captions'],
                ]);
            }
        }
        $tip->unsetRelation('slides');
    }

    /** Files the tip uses now (cover + slides), to delete those it no longer uses after a save. */
    private function files(TechTip $tip, TipSlides $slides): array
    {
        return array_filter([$tip->image, ...$slides->mediaPaths($tip->slides()->with('texts')->get()->map($slides->fromModel(...))->all())]);
    }

    /** "Save" goes back to the list, "Save and keep editing" stays on the form. */
    private function saved(Request $request, TechTip $tip): RedirectResponse
    {
        return ($request->input('then') === 'edit' ? redirect()->route('admin.tech-tips.edit', $tip) : redirect()->route('admin.tech-tips.index'))
            ->with('success', __('Tip saved.'));
    }
}
