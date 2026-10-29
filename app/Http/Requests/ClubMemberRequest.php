<?php

namespace App\Http\Requests;

use App\Models\ClubMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / update a sport club member. Also used for every row of an Excel import. */
class ClubMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('club.members.manage');
    }

    public function rules(): array
    {
        $member = $this->route('member');

        // Self-registered members may have only a name until an admin completes them.
        $requireContact = ! ($member instanceof ClubMember && $member->is_pending);

        return self::fieldRules($member instanceof ClubMember ? $member->id : null, $requireContact) + [
            'is_active' => ['boolean'],
        ];
    }

    /** $ignoreId = false skips the unique check (the importer finds duplicates itself). */
    public static function fieldRules(int|false|null $ignoreId = null, bool $requireContact = true): array
    {
        $personalId = ['nullable', ...($requireContact ? ['required_without:phone'] : []), 'string', 'regex:'.VisitorRequest::PERSONAL_ID_REGEX];
        $phone = ['nullable', ...($requireContact ? ['required_without:personal_id'] : []), 'string', 'regex:'.VisitorRequest::PHONE_REGEX];
        $email = ['nullable', 'string', 'max:255', 'email:rfc,strict', 'regex:'.VisitorRequest::EMAIL_REGEX];
        if ($ignoreId !== false) {
            $personalId[] = Rule::unique('club_members', 'personal_id')->ignore($ignoreId);
            $phone[] = Rule::unique('club_members', 'phone')->ignore($ignoreId);
            $email[] = Rule::unique('club_members', 'email')->ignore($ignoreId);
        }

        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.VisitorRequest::NAME_REGEX],
            'last_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.VisitorRequest::NAME_REGEX],
            'personal_id' => $personalId,
            'phone' => $phone,
            'email' => $email,
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::in(array_keys(ClubMember::GENDERS))],
            'emergency_contact_name' => ['nullable', 'string', 'min:2', 'max:150', 'regex:'.VisitorRequest::NAME_REGEX],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:'.VisitorRequest::PHONE_REGEX],
            'joined_on' => ['nullable', 'date', 'after:1900-01-01'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The member card on the public check-in pages: every field of ClubMember::PROFILE_FIELDS is required.
     * $only limits the rules to some fields (the "complete your details" screen asks only for the missing ones).
     */
    public static function profileRules(?int $ignoreId = null, ?array $only = null): array
    {
        $rules = array_intersect_key(self::fieldRules($ignoreId, false), array_flip($only ?? ClubMember::PROFILE_FIELDS));

        return array_map(fn (array $field) => ['required', ...array_diff($field, ['nullable'])], $rules);
    }

    /** VisitorRequest::normalize() plus the emergency contact. */
    public static function normalize(array $data): array
    {
        $data = VisitorRequest::normalize($data);
        if (isset($data['emergency_contact_phone']) && is_string($data['emergency_contact_phone'])) {
            $data['emergency_contact_phone'] = preg_replace('/[\s\-().+]/', '', $data['emergency_contact_phone']);
        }
        if (isset($data['emergency_contact_name']) && is_string($data['emergency_contact_name'])) {
            $data['emergency_contact_name'] = preg_replace('/\s+/', ' ', trim($data['emergency_contact_name']));
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->only(['personal_id', 'phone', 'first_name', 'last_name', 'email', 'emergency_contact_name', 'emergency_contact_phone'])));
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public static function fieldMessages(): array
    {
        return VisitorRequest::fieldMessages() + [
            'personal_id.unique' => __('A club member with this ID number already exists.'),
            'phone.unique' => __('A club member with this phone number already exists.'),
            'email.unique' => __('A club member with this email already exists.'),
            'personal_id.required_without' => __('Enter the ID number or the phone number.'),
            'phone.required_without' => __('Enter the ID number or the phone number.'),
            'emergency_contact_name.regex' => __('The name may contain only letters, spaces and dashes.'),
            'emergency_contact_phone.regex' => __('The phone number must contain only digits (9–15).'),
        ];
    }

    public function attributes(): array
    {
        return self::fieldAttributes();
    }

    public static function fieldAttributes(): array
    {
        return [
            'personal_id' => __('ID number'), 'birth_date' => __('date of birth'), 'joined_on' => __('member since'),
            'emergency_contact_name' => __('emergency contact'), 'emergency_contact_phone' => __('emergency contact phone'),
        ];
    }
}
