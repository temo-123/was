<?php

namespace App\Http\Requests;

use App\Models\VisitorVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Register a visitor entrance: either an existing visitor_id, or a new "visitor" object. */
class EntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can('visits.create') && ($this->filled('visitor_id') || $user->can('visitors.create'));
    }

    public function rules(): array
    {
        return [
            'visitor_id' => ['nullable', 'required_without:visitor', 'integer', Rule::exists('visitors', 'id')->whereNull('deleted_at')],
            'visitor' => ['nullable', 'required_without:visitor_id', 'array'],
            ...collect(VisitorRequest::fieldRules('visitor.'))
                ->map(fn ($rules) => array_map(fn ($rule) => $rule === 'required' ? 'required_without:visitor_id' : $rule, $rules))
                ->all(),
            'terms_accepted' => ['boolean'],
            'ticket_type_id' => ['required', Rule::exists('ticket_types', 'id')->where('is_active', true)],
            'visitor_category_id' => ['nullable', 'exists:visitor_categories,id'],
            'payment_method' => ['nullable', Rule::in(VisitorVisit::PAYMENT_METHODS)],
            'use_subscription' => ['boolean'],
            'price_override' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('visitor'))) {
            $this->merge(['visitor' => VisitorRequest::normalize($this->input('visitor'))]);
        }
    }

    public function messages(): array
    {
        return VisitorRequest::fieldMessages('visitor.');
    }

    public function attributes(): array
    {
        return [
            'visitor.first_name' => __('first name'),
            'visitor.last_name' => __('last name'),
            'visitor.personal_id' => __('ID number'),
            'visitor.birth_date' => __('date of birth'),
            'visitor.phone' => __('phone'),
            'visitor.email' => __('email'),
            'terms_accepted' => __('gym terms'),
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->filled('visitor_id') && ! $this->boolean('terms_accepted') && ! $this->boolean('visitor.terms_accepted')) {
                    $validator->errors()->add('terms_accepted', __('The visitor must accept the gym terms.'));
                }
            },
        ];
    }

    /** Validated data in the shape EntryService::register() expects. */
    public function entryData(): array
    {
        $data = $this->validated();
        $data['terms_accepted'] = $this->boolean('terms_accepted') || $this->boolean('visitor.terms_accepted');
        $data['use_subscription'] = $this->boolean('use_subscription', true);

        if (empty($data['visitor_id'])) {
            $data['visitor'] = collect($data['visitor'])
                ->only(array_map(fn ($key) => substr($key, 8), array_keys(VisitorRequest::fieldRules('visitor.'))))
                ->put('terms_accepted', true)
                ->all();
        }

        return $data;
    }
}
