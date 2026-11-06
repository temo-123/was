<?php

namespace App\Http\Requests;

use App\Services\Tips\TipMedia;
use App\Services\Tips\TipSlides;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / update (or preview) a tech tip, as sent by the form + TipEditor.vue:
 * settings, `image` (cover: file name uploaded by the editor), articles[ka|en][title, short_description]
 * and `slides` (JSON, see App\Services\Tips\TipSlides). A language without a title isn't shown on the tablet,
 * but at least one language and one slide are needed.
 */
class TechTipRequest extends FormRequest
{
    private ?array $slidesCache = null;

    public function authorize(): bool
    {
        return $this->user()->can('tips.manage');
    }

    public function rules(): array
    {
        $rules = [
            'category' => ['required', Rule::in(array_keys(config('tips.categories')))],
            'level' => ['nullable', Rule::in(array_keys(config('tips.levels')))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['boolean'],
            'show_warning' => ['boolean'],
            'image' => ['nullable', 'string', 'max:255'],
            'slides' => ['nullable', 'json', 'max:1000000'],
        ];

        foreach (array_keys(config('app.locales')) as $locale) {
            $rules["articles.$locale.title"] = ['nullable', 'string', 'max:255'];
            $rules["articles.$locale.short_description"] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $titled = array_filter($this->articles(), fn ($a) => filled($a['title']));
            foreach ($this->articles() as $locale => $article) {
                if (blank($article['title']) && filled($article['short_description'])) {
                    $validator->errors()->add("articles.$locale.title", __('The tip needs a title in this language too.'));
                }
            }
            if ($titled === []) {
                $validator->errors()->add('articles', __('Write the title in at least one language.'));
            }
            if ($this->slides() === []) {
                $validator->errors()->add('slides', __('Add at least one slide with a picture or a text.'));
            }
        }];
    }

    /** @return array<string, array{title: string, short_description: ?string}> by language */
    public function articles(): array
    {
        return collect(array_keys(config('app.locales')))->mapWithKeys(function ($locale) {
            $input = (array) $this->input("articles.$locale", []);

            return [$locale => [
                'title' => trim(preg_replace('/\s+/u', ' ', (string) ($input['title'] ?? ''))),
                'short_description' => filled($input['short_description'] ?? null) ? trim($input['short_description']) : null,
            ]];
        })->all();
    }

    /** The slides as they will be saved (TipSlides::clean). */
    public function slides(): array
    {
        return $this->slidesCache ??= app(TipSlides::class)->clean(json_decode((string) $this->input('slides', '[]'), true));
    }

    /** Cover: disk path of an uploaded picture, or null. */
    public function cover(): ?string
    {
        $media = app(TipMedia::class);
        $path = $media->pathOf($this->input('image'));

        return $path && ! $media->isVideo($path) ? $path : null;
    }

    public function attributes(): array
    {
        $names = ['image' => __('Cover picture'), 'slides' => __('Slides')];
        foreach (config('app.locales') as $locale => $language) {
            $names["articles.$locale.title"] = __('Title')." ($language)";
            $names["articles.$locale.short_description"] = __('Short description')." ($language)";
        }

        return $names;
    }
}
