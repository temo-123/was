<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One language of a tech tip's start screen: title and short description. A tip is shown in the languages that have one. */
#[Fillable(['locale', 'title', 'short_description'])]
class TechTipArticle extends Model
{
    public function tip(): BelongsTo
    {
        return $this->belongsTo(TechTip::class, 'tech_tip_id');
    }
}
