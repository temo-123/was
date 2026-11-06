<?php

namespace App\Services\Tips;

use App\Models\TechTip;
use App\Models\TechTipArticle;
use App\Models\TechTipSlide;

/** A tip the way the tablet page (TechTips.vue) gets it: start screen in every language + the slides. */
class TipPresenter
{
    public function __construct(private TipSlides $slides, private TipMedia $media) {}

    public function present(TechTip $tip, ?array $slides = null): array
    {
        $slides ??= $tip->slides->map(fn (TechTipSlide $s) => $this->slides->fromModel($s))->all();

        return [
            'id' => $tip->id,
            'category' => $tip->category,
            'level' => $tip->level,
            'warning' => $tip->show_warning,
            'image' => $this->media->url($tip->image),
            'articles' => $tip->articles->mapWithKeys(fn (TechTipArticle $a) => [$a->locale => [
                'title' => $a->title,
                'short' => $a->short_description,
                'words' => $this->slides->words($slides, $a->locale), // for "3 min read"
            ]]),
            'slides' => array_map($this->slides->render(...), $slides),
        ];
    }
}
