<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One slide of a tech tip. Shared by all languages: layout, pictures / video (`media`: [{id, src, mark}]),
 * white background for drawings, note box colour and the "more info" tip. Texts: TechTipSlideText per language.
 * See App\Services\Tips\TipSlides for the layouts.
 */
#[Fillable(['position', 'layout', 'media', 'light', 'note_variant', 'link_tip_id'])]
class TechTipSlide extends Model
{
    protected function casts(): array
    {
        return ['media' => 'array', 'light' => 'boolean', 'position' => 'integer'];
    }

    public function tip(): BelongsTo
    {
        return $this->belongsTo(TechTip::class, 'tech_tip_id');
    }

    public function texts(): HasMany
    {
        return $this->hasMany(TechTipSlideText::class);
    }
}
