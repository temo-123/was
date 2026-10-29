<?php

namespace App\Services\Club;

use App\Http\Requests\ClubMemberRequest;
use App\Http\Requests\VisitorRequest;
use App\Models\ActivityLog;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Checks imported rows against existing members (by ID number, phone and email) and
 * against each other, then creates only the new ones.
 */
class MemberImporter
{
    public const NEW = 'new';

    public const DUPLICATE = 'duplicate';

    public const INVALID = 'invalid';

    private const MATCH_FIELDS = ['personal_id' => 'ID number', 'phone' => 'Phone', 'email' => 'Email'];

    /**
     * @param  array<int, array<string, string|null>>  $rows  line number => fields
     * @return array<int, array{line: int, data: array, status: string, errors: array, match: ?array}>
     */
    public function analyze(array $rows): array
    {
        $existing = [];
        foreach (array_keys(self::MATCH_FIELDS) as $field) {
            $existing[$field] = ClubMember::whereNotNull($field)->pluck('id', $field)->all();
        }
        $names = [];
        $inFile = []; // field => value => line

        $result = [];
        foreach ($rows as $line => $data) {
            $data = VisitorRequest::normalize($data);
            $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
            $item = ['line' => $line, 'data' => $data, 'status' => self::NEW, 'errors' => [], 'match' => null];

            $validator = Validator::make($data, ClubMemberRequest::fieldRules(false), ClubMemberRequest::fieldMessages());
            if ($validator->fails()) {
                $item['status'] = self::INVALID;
                $item['errors'] = $validator->errors()->all();
            }

            foreach (self::MATCH_FIELDS as $field => $label) {
                $value = $data[$field] ?? null;
                if (! $value) {
                    continue;
                }
                if (isset($existing[$field][$value])) {
                    $id = $existing[$field][$value];
                    $names[$id] ??= ClubMember::find($id)?->full_name;
                    $item['status'] = self::DUPLICATE;
                    $item['match'] = ['id' => $id, 'name' => $names[$id], 'field' => __($label), 'value' => $value];
                    break;
                }
                if (isset($inFile[$field][$value])) {
                    $item['status'] = self::DUPLICATE;
                    $item['match'] = ['id' => null, 'name' => __('row :line of this file', ['line' => $inFile[$field][$value]]), 'field' => __($label), 'value' => $value];
                    break;
                }
            }

            if ($item['status'] === self::NEW) {
                foreach (array_keys(self::MATCH_FIELDS) as $field) {
                    if ($data[$field] ?? null) {
                        $inFile[$field][$data[$field]] = $line;
                    }
                }
            }

            $result[$line] = $item;
        }

        return $result;
    }

    /**
     * Re-checks the rows (the member list may have changed since the preview) and creates the new members.
     *
     * @return array{created: int, duplicates: int, invalid: int}
     */
    public function import(array $rows, ?User $user): array
    {
        $items = $this->analyze($rows);
        $counts = ['created' => 0, 'duplicates' => 0, 'invalid' => 0];

        DB::transaction(function () use ($items, $user, &$counts) {
            foreach ($items as $item) {
                if ($item['status'] === self::NEW) {
                    ClubMember::create($item['data'] + ['is_active' => true, 'created_by' => $user?->id]);
                    $counts['created']++;
                } else {
                    $counts[$item['status'] === self::DUPLICATE ? 'duplicates' : 'invalid']++;
                }
            }
        });

        ActivityLog::record('club_member.imported', null, $counts);

        return $counts;
    }

    /** @return array{new: int, duplicate: int, invalid: int} */
    public static function summary(array $items): array
    {
        $summary = [self::NEW => 0, self::DUPLICATE => 0, self::INVALID => 0];
        foreach ($items as $item) {
            $summary[$item['status']]++;
        }

        return $summary;
    }
}
