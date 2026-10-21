<?php

namespace App\Http\Requests;

use App\Models\Visitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / update a visitor (admin panel and API). */
class VisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('visitor') ? 'visitors.update' : 'visitors.create');
    }

    public function rules(): array
    {
        $visitor = $this->route('visitor');

        return self::fieldRules('', $visitor instanceof Visitor ? $visitor->id : null) + [
            'terms_accepted' => [$visitor ? 'boolean' : 'accepted'],
        ];
    }

    public const PERSONAL_ID_REGEX = '/^\d{11}$/';

    public const PHONE_REGEX = '/^\d{9,15}$/';

    public const NAME_REGEX = "/^[\\pL][\\pL\\s'\\-]*$/u";

    public const EMAIL_REGEX = '/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/';

    /** Shared with the entrance form, where the fields are nested under "visitor.". */
    public static function fieldRules(string $prefix = '', ?int $ignoreId = null): array
    {
        return [
            $prefix.'first_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.self::NAME_REGEX],
            $prefix.'last_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.self::NAME_REGEX],
            $prefix.'personal_id' => ['required', 'string', 'regex:'.self::PERSONAL_ID_REGEX, Rule::unique('visitors', 'personal_id')->ignore($ignoreId)],
            $prefix.'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            $prefix.'phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
            $prefix.'email' => ['nullable', 'string', 'max:255', 'email:rfc,strict', 'regex:'.self::EMAIL_REGEX],
            $prefix.'comment' => ['nullable', 'string', 'max:2000'],
            $prefix.'visitor_category_id' => ['nullable', 'exists:visitor_categories,id'],
        ];
    }

    /** Remove spaces/dashes people type in numbers, trim names, lower-case email. */
    public static function normalize(array $data): array
    {
        foreach (['personal_id', 'phone'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = preg_replace('/[\s\-().+]/', '', $data[$field]);
            }
        }
        foreach (['first_name', 'last_name'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = preg_replace('/\s+/', ' ', trim($data[$field]));
            }
        }
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email'])) ?: null;
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->only(['personal_id', 'phone', 'first_name', 'last_name', 'email'])));
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public static function fieldMessages(string $prefix = ''): array
    {
        return [
            $prefix.'personal_id.regex' => __('The ID number must be exactly 11 digits.'),
            $prefix.'personal_id.unique' => __('A visitor with this ID number already exists.'),
            $prefix.'phone.regex' => __('The phone number must contain only digits (9–15).'),
            $prefix.'email.regex' => __('Enter a real email address, e.g. name@gmail.com.'),
            $prefix.'email.email' => __('Enter a real email address, e.g. name@gmail.com.'),
            $prefix.'first_name.regex' => __('The name may contain only letters, spaces and dashes.'),
            $prefix.'last_name.regex' => __('The surname may contain only letters, spaces and dashes.'),
            $prefix.'birth_date.before' => __('The date of birth must be in the past.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'personal_id' => __('ID number'),
            'birth_date' => __('date of birth'),
            'terms_accepted' => __('gym terms'),
        ];
    }
}
