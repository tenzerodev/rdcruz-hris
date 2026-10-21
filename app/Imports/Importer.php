<?php

namespace App\Imports;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * One CSV import (one section). Subclasses describe their columns — used for the template, the
 * instructions page and validation — and save one valid row at a time.
 */
abstract class Importer
{
    /** @var array<string, ?Employee> */
    private array $employees = [];

    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * @return list<array{name: string, label: string, required?: bool, format: string, example: string, rules?: array}>
     */
    abstract public function columns(): array;

    /** Extra example rows for the template (first example comes from the columns). */
    public function moreExamples(): array
    {
        return [];
    }

    /** Notes shown on the instructions page. */
    public function notes(): array
    {
        return [];
    }

    /** Where the section lives, for "Back" links. */
    public function sectionRoute(): string
    {
        return 'admin.imports.index';
    }

    /** Save one validated, normalized row. Return 'created', 'updated' or 'skipped'. */
    abstract public function save(array $row): string;

    /** Extra checks after the column rules (e.g. lookups). Return error messages. */
    protected function check(array $row): array
    {
        return [];
    }

    /**
     * Checks that need the whole file (e.g. references to other rows).
     *
     * @param  list<array>  $rows  clean rows that passed the row checks
     * @return array<int, list<string>> line number => errors
     */
    public function fileChecks(array $rows): array
    {
        return [];
    }

    /** Runs after all rows are saved (e.g. second pass for cross-references). */
    public function finish(array $rows): void {}

    // ---- shared machinery ------------------------------------------------------

    public function headers(): array
    {
        return array_column($this->columns(), 'name');
    }

    public function exampleRows(): array
    {
        return [array_column($this->columns(), 'example'), ...$this->moreExamples()];
    }

    /**
     * Normalize and validate one raw row (header => value).
     *
     * @return array{0: array, 1: list<string>} [clean row, errors]
     */
    public function validate(array $raw): array
    {
        $row = [];
        $rules = [];
        $errors = [];
        foreach ($this->columns() as $col) {
            $value = trim((string) ($raw[$col['name']] ?? ''));
            $format = $col['format'];
            if ($value !== '' && str_starts_with($format, 'Date')) {
                $parsed = self::date($value);
                if ($parsed === null) {
                    $errors[] = "{$col['label']}: “{$value}” is not a date (use YYYY-MM-DD or MM/DD/YYYY).";
                    $value = '';
                } else {
                    $value = $parsed;
                }
            } elseif ($value !== '' && str_starts_with($format, 'Time')) {
                $parsed = self::time($value);
                if ($parsed === null) {
                    $errors[] = "{$col['label']}: “{$value}” is not a time (use HH:MM, e.g. 08:05 or 5:30 PM).";
                    $value = '';
                } else {
                    $value = $parsed;
                }
            } elseif ($value !== '' && str_starts_with($format, 'Number')) {
                $value = str_replace([',', '₱', ' '], '', $value);
            }
            $row[$col['name']] = $value === '' ? null : $value;
            $rules[$col['name']] = array_merge([($col['required'] ?? false) ? 'required' : 'nullable'], $col['rules'] ?? []);
        }

        $v = Validator::make($row, $rules, [], array_column($this->columns(), 'label', 'name'));
        $errors = array_merge($errors, $v->errors()->all());
        if (! $errors) {
            $errors = $this->check($row);
        }

        return [$row, $errors];
    }

    /**
     * Parse CSV text into rows keyed by our column names (headers matched loosely).
     *
     * @return array{rows: list<array>, unknown: list<string>, missing: list<string>}
     */
    public function parse(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // Excel's UTF-8 BOM
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $firstLine = strtok($content, "\n");
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $norm = fn ($h) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $h))), '_');
        $header = array_map($norm, fgetcsv($handle, null, $delimiter, '"', '') ?: []);
        $known = $this->headers();

        $rows = [];
        $line = 1;
        while (($data = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach ($header as $i => $h) {
                if (in_array($h, $known, true)) {
                    $assoc[$h] = $data[$i] ?? '';
                }
            }
            $assoc['_line'] = $line;
            $rows[] = $assoc;
        }
        fclose($handle);

        $required = array_column(array_filter($this->columns(), fn ($c) => $c['required'] ?? false), 'name');

        return [
            'rows' => $rows,
            'unknown' => array_values(array_diff(array_filter($header), $known)),
            'missing' => array_values(array_diff($required, $header)),
        ];
    }

    protected function employee(?string $no): ?Employee
    {
        if ($no === null || $no === '') {
            return null;
        }

        return $this->employees[$no] ??= Employee::where('employee_no', $no)->first();
    }

    protected function forgetEmployee(string $no): void
    {
        unset($this->employees[$no]);
    }

    /** Accepts YYYY-MM-DD, MM/DD/YYYY, "Oct 5, 2026" and Excel serial numbers. */
    public static function date(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('/^\d{5}$/', $v) && $v > 20000 && $v < 80000) { // Excel serial date
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        foreach (['Y-m-d', 'n/j/Y', 'Y/n/j', 'n-j-Y', 'j-M-Y', 'M j, Y', 'F j, Y', 'n/j/y'] as $f) {
            $d = \DateTimeImmutable::createFromFormat('!'.$f, $v);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($d && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && (int) $d->format('Y') > 1900) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    public static function time(string $v): ?string
    {
        $v = strtoupper(trim(str_replace('.', '', $v)));
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/', $v, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            if (! empty($m[3])) {
                if ($h < 1 || $h > 12) {
                    return null;
                }
                $h = $m[3] === 'PM' ? ($h % 12) + 12 : $h % 12;
            }

            return $h < 24 && $i < 60 ? sprintf('%02d:%02d', $h, $i) : null;
        }

        return null;
    }
}
