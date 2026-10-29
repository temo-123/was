<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/** Price list items have an English `name` and a Georgian `name_ka`: $model->local_name picks the one for the site language. */
trait HasLocalName
{
    protected function localName(): Attribute
    {
        return Attribute::get(fn () => app()->getLocale() === 'ka' && filled($this->name_ka) ? $this->name_ka : $this->name);
    }
}
