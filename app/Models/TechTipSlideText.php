<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The texts of one slide in one language: title, text and note (Markdown), picture captions by media id. */
#[Fillable(['locale', 'title', 'body', 'note', 'captions'])]
class TechTipSlideText extends Model
{
    protected function casts(): array
    {
        return ['captions' => 'array'];
    }

    public function slide(): BelongsTo
    {
        return $this->belongsTo(TechTipSlide::class, 'tech_tip_slide_id');
    }
}
