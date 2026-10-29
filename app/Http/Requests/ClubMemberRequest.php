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
        if ($ignoreId !== false) {
            $personalId[] = Rule::unique('club_members', 'personal_id')->ignore($ignoreId);
        }

        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.VisitorRequest::NAME_REGEX],
            'last_name' => ['required', 'string', 'min:2', 'max:100', 'regex:'.VisitorRequest::NAME_REGEX],
            'personal_id' => $personalId,
            'phone' => ['nullable', ...($requireContact ? ['required_without:personal_id'] : []), 'string', 'regex:'.VisitorRequest::PHONE_REGEX],
            'email' => ['nullable', 'string', 'max:255', 'email:rfc,strict', 'regex:'.VisitorRequest::EMAIL_REGEX],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'joined_on' => ['nullable', 'date', 'after:1900-01-01'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(VisitorRequest::normalize($this->only(['personal_id', 'phone', 'first_name', 'last_name', 'email'])));
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public static function fieldMessages(): array
    {
        return VisitorRequest::fieldMessages() + [
            'personal_id.unique' => __('A club member with this ID number already exists.'),
            'personal_id.required_without' => __('Enter the ID number or the phone number.'),
            'phone.required_without' => __('Enter the ID number or the phone number.'),
        ];
    }

    public function attributes(): array
    {
        return ['personal_id' => __('ID number'), 'birth_date' => __('date of birth'), 'joined_on' => __('member since')];
    }
}
