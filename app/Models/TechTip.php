<?php

namespace App\Models;

use App\Services\Tips\TipMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A climbing tip shown on the gym tablet as a slide show: start screen (TechTipArticle: title + short description
 * per language, kaArticle / enArticle, and the shared cover `image`) → slides (TechTipSlide). What all languages
 * share is stored once, only texts are per language.
 */
#[Fillable(['category', 'level', 'show_warning', 'image', 'sort_order', 'is_published', 'created_by'])]
class TechTip extends Model
{
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'show_warning' => 'boolean',
            'views' => 'integer',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(TechTipArticle::class);
    }

    public function kaArticle(): HasOne
    {
        return $this->hasOne(TechTipArticle::class)->where('locale', 'ka');
    }

    public function enArticle(): HasOne
    {
        return $this->hasOne(TechTipArticle::class)->where('locale', 'en');
    }

    public function slides(): HasMany
    {
        return $this->hasMany(TechTipSlide::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->whereHas('articles')->whereHas('slides');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('id');
    }

    /** The text in the given language, or in any other language when it hasn't been translated. */
    public function article(?string $locale = null): ?TechTipArticle
    {
        $locale ??= app()->getLocale();

        return $this->articles->firstWhere('locale', $locale) ?? $this->articles->first();
    }

    public function imageUrl(): ?string
    {
        return app(TipMedia::class)->url($this->image);
    }

    public function categoryLabel(): string
    {
        return __(config("tips.categories.{$this->category}.label", $this->category));
    }

    public function categoryIcon(): string
    {
        return config("tips.categories.{$this->category}.icon", 'bi-lightbulb');
    }

    public function levelLabel(): ?string
    {
        return $this->level ? __(config("tips.levels.{$this->level}")) : null;
    }
}
