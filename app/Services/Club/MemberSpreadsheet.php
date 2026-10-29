<?php

namespace App\Services\Club;

use App\Models\ClubMember;
use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/** Reads club members from .xlsx / .ods / .csv files and writes them to .xlsx. */
class MemberSpreadsheet
{
    /** Column => header used in exports and in the import template. */
    public const COLUMNS = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'personal_id' => 'ID number',
        'phone' => 'Phone',
        'email' => 'Email',
        'birth_date' => 'Date of birth',
        'joined_on' => 'Member since',
        'comment' => 'Comment',
    ];

    /** Header names recognised on import (lower case, spaces only). */
    private const HEADER_ALIASES = [
        'first_name' => ['first name', 'firstname', 'name', 'given name', 'სახელი'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name', 'გვარი'],
        'full_name' => ['full name', 'name surname', 'name and surname', 'სახელი გვარი', 'სახელი და გვარი'],
        'personal_id' => ['id number', 'id', 'personal id', 'personal number', 'id no', 'პირადი ნომერი', 'პირადი #', 'პ ნ', 'პ/ნ'],
        'phone' => ['phone', 'phone number', 'mobile', 'telephone', 'tel', 'ტელეფონი', 'ტელ', 'მობილური', 'ტელეფონის ნომერი'],
        'email' => ['email', 'e mail', 'mail', 'ელფოსტა', 'ელ ფოსტა', 'ელ-ფოსტა'],
        'birth_date' => ['date of birth', 'birth date', 'birthday', 'dob', 'დაბადების თარიღი', 'დაბადების დღე'],
        'joined_on' => ['member since', 'joined', 'joined on', 'join date', 'გაწევრიანების თარიღი', 'გაწევრიანდა'],
        'comment' => ['comment', 'comments', 'note', 'notes', 'კომენტარი', 'შენიშვნა'],
    ];

    public const EXTENSIONS = ['xlsx', 'ods', 'csv'];

    /**
     * Rows of the first sheet as [line number => [field => string|null]], header row excluded.
     *
     * @return array<int, array<string, string|null>>
     */
    public function read(string $path, string $extension): array
    {
        $reader = match (strtolower($extension)) {
            'xlsx' => new XlsxReader,
            'ods' => new OdsReader,
            'csv' => new CsvReader(new CsvOptions(FIELD_DELIMITER: $this->csvDelimiter($path))),
            default => throw new RuntimeException(__('Unsupported file type. Use .xlsx, .ods or .csv.')),
        };

        $reader->open($path);
        try {
            $map = null;
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    // Rich text cells come back as a list of text runs.
                    $values = array_map(fn ($value) => is_array($value) ? implode('', array_map(fn ($run) => $run->text, $value)) : $value, $row->toArray());
                    if ($this->isEmpty($values)) {
                        continue;
                    }
                    if ($map === null) {
                        $map = $this->headerMap($values);
                        if (! $map) {
                            throw new RuntimeException(__('Column names were not recognised. Download the template and keep its first row.'));
                        }

                        continue;
                    }
                    $rows[$line] = $this->mapRow($values, $map);
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /** Writes members to a temporary .xlsx file and returns its path. */
    public function write(iterable $members, bool $withAttendance = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'members').'.xlsx';
        $writer = new Writer;
        $writer->getOptions()->setColumnWidth(18, 1, 2, 3, 4, 6, 7);
        $writer->getOptions()->setColumnWidth(28, 5, 8);
        $writer->openToFile($path);

        $headers = array_map(fn ($label) => __($label), array_values(self::COLUMNS));
        if ($withAttendance) {
            $headers[] = __('Meetings attended');
        }
        $writer->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)));

        $date = (new Style)->withFormat('dd.mm.yyyy');
        foreach ($members as $member) {
            /** @var ClubMember $member */
            $cells = [
                Cell::fromValue($member->first_name),
                Cell::fromValue($member->last_name),
                Cell::fromValue((string) $member->personal_id), // text, so leading zeros stay
                Cell::fromValue((string) $member->phone),
                Cell::fromValue((string) $member->email),
                Cell::fromValue($member->birth_date?->toDateTimeImmutable(), $date),
                Cell::fromValue($member->joined_on?->toDateTimeImmutable(), $date),
                Cell::fromValue((string) $member->comment),
            ];
            if ($withAttendance) {
                $cells[] = Cell::fromValue((int) $member->attendances_count);
            }
            $writer->addRow(new Row($cells));
        }
        $writer->close();

        return $path;
    }

    /** Excel saves CSV with ";" in many locales. */
    private function csvDelimiter(string $path): string
    {
        $line = (string) fgets($handle = fopen($path, 'r'));
        fclose($handle);

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private function isEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) ($value instanceof DateTimeInterface ? 'x' : $value)) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> column index => field */
    private function headerMap(array $values): array
    {
        $map = [];
        foreach ($values as $index => $value) {
            $header = $this->normalizeHeader((string) ($value instanceof DateTimeInterface ? '' : $value));
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                $translated = isset(self::COLUMNS[$field]) ? [$this->normalizeHeader(__(self::COLUMNS[$field]))] : [];
                if (in_array($header, array_merge($aliases, $translated), true) && ! in_array($field, $map, true)) {
                    $map[$index] = $field;
                    break;
                }
            }
        }

        $fields = array_values($map);
        $hasName = in_array('full_name', $fields, true) || in_array('first_name', $fields, true);

        return $hasName ? $map : [];
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/[\s_\-.:*№#()]+/u', ' ', $header);

        return trim($header);
    }

    /** @return array<string, string|null> */
    private function mapRow(array $values, array $map): array
    {
        $row = array_fill_keys(array_keys(self::COLUMNS), null);

        foreach ($map as $index => $field) {
            $value = $values[$index] ?? null;
            $row[$field] = match ($field) {
                'birth_date', 'joined_on' => $this->toDate($value),
                'personal_id' => $this->toDigits($value, 11),
                'phone' => $this->toDigits($value),
                default => $this->toText($value),
            };
        }

        // "Full name" column, or a first name column that holds "Name Surname".
        $full = $row['full_name'] ?? null;
        unset($row['full_name']);
        if ($full && ! $row['first_name']) {
            [$row['first_name'], $row['last_name']] = array_pad(preg_split('/\s+/', $full, 2), 2, $row['last_name']);
        } elseif ($row['first_name'] && ! $row['last_name'] && str_contains($row['first_name'], ' ')) {
            [$row['first_name'], $row['last_name']] = preg_split('/\s+/', $row['first_name'], 2);
        }

        return $row;
    }

    private function toText(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Numbers typed into Excel lose leading zeros: 1001012345 → 01001012345. */
    private function toDigits(mixed $value, ?int $length = null): ?string
    {
        $text = $this->toText($value);
        if ($text === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $length ? str_pad($text, $length, '0', STR_PAD_LEFT) : $text;
        }

        return $text;
    }

    private function toDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_int($value) || is_float($value)) {
            // Excel date serial number (a date cell without date formatting).
            return $value > 0 && $value < 100000 ? Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString() : (string) $value;
        }

        $text = $this->toText($value);
        if ($text === null) {
            return null;
        }
        foreach (['d.m.Y', 'd/m/Y', 'd-m-Y', 'Y-m-d', 'Y.m.d', 'Y/m/d', 'd.m.y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ! $errors && (int) $date->format('Y') >= 1900) {
                return $date->format('Y-m-d');
            }
        }

        return $text; // left as is; validation reports it
    }
}
