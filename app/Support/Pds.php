<?php

namespace App\Support;

use App\Models\Employee;

/**
 * The parts of the Personal Data Sheet (CSC Form 212, Revised 2017) kept in employees.pds.
 * Everything else (name, birth date, IDs, education, eligibility, L&D, service) comes from the 201 tables.
 */
class Pds
{
    public const ADDRESS = ['house', 'street', 'subdivision', 'barangay', 'city', 'province', 'zip'];

    public const PERSONAL = ['place_of_birth', 'citizenship', 'dual_by', 'dual_country', 'height', 'weight', 'blood_type', 'umid_no', 'philsys_no', 'telephone'];

    public const LISTS = [
        'children' => ['name', 'birth_date'],
        'work_experience' => ['from', 'to', 'position', 'company', 'monthly_salary', 'salary_grade', 'status', 'government'],
        'voluntary' => ['organization', 'from', 'to', 'hours', 'position'],
        'references' => ['name', 'address', 'telephone'],
    ];

    public const TEXT_LISTS = ['skills', 'recognitions', 'memberships'];

    public const QUESTIONS = [
        '34a' => '34. Are you related by consanguinity or affinity to the appointing or recommending authority, or to the chief of bureau or office or to the person who has immediate supervision over you in the Office, Bureau or Department where you will be appointed, a. within the third degree?',
        '34b' => 'b. within the fourth degree (for Local Government Unit - Career Employees)?',
        '35a' => '35. a. Have you ever been found guilty of any administrative offense?',
        '35b' => 'b. Have you been criminally charged before any court? (give date filed and status of case)',
        '36' => '36. Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?',
        '37' => '37. Have you ever been separated from the service in any of the following modes: resignation, retirement, dropped from the rolls, dismissal, termination, end of term, finished contract or phased out (abolition) in the public or private sector?',
        '38a' => '38. a. Have you ever been a candidate in a national or local election held within the last year (except Barangay election)?',
        '38b' => 'b. Have you resigned from the government service during the three (3)-month period before the last election to promote/actively campaign for a national or local candidate?',
        '39' => '39. Have you acquired the status of an immigrant or permanent resident of another country?',
        '40a' => '40. a. Are you a member of any indigenous group?',
        '40b' => 'b. Are you a person with disability?',
        '40c' => 'c. Are you a solo parent?',
    ];

    /** Keep only known fields, as trimmed strings, with sane limits. */
    public static function clean(array $in): array
    {
        $s = fn ($v, int $max = 250) => is_scalar($v) ? mb_substr(trim((string) $v), 0, $max) : '';
        $pick = fn (array $src, array $keys, int $max = 250) => collect($keys)->mapWithKeys(fn ($k) => [$k => $s($src[$k] ?? '', $max)])->all();

        $out = [
            'personal' => $pick($in['personal'] ?? [], self::PERSONAL),
            'residential' => $pick($in['residential'] ?? [], self::ADDRESS),
            'permanent' => $pick($in['permanent'] ?? [], self::ADDRESS),
            'spouse' => $pick($in['spouse'] ?? [], ['surname', 'first', 'middle', 'ext', 'occupation', 'employer', 'business_address', 'telephone']),
            'father' => $pick($in['father'] ?? [], ['surname', 'first', 'middle', 'ext']),
            'mother' => $pick($in['mother'] ?? [], ['surname', 'first', 'middle']),
            'gov_id' => $pick($in['gov_id'] ?? [], ['type', 'number', 'issued']),
            'questions' => [],
        ];
        foreach (self::LISTS as $list => $keys) {
            $rows = array_slice(array_values(is_array($in[$list] ?? null) ? $in[$list] : []), 0, 40);
            $out[$list] = array_values(array_filter(array_map(fn ($r) => is_array($r) ? $pick($r, $keys) : null, $rows),
                fn ($r) => $r && implode('', $r) !== ''));
        }
        foreach (self::TEXT_LISTS as $list) {
            $out[$list] = array_values(array_filter(array_map(fn ($l) => $s($l), preg_split('/\R/', (string) ($in[$list] ?? '')))));
        }
        foreach (array_keys(self::QUESTIONS) as $q) {
            $answer = $in['questions'][$q]['answer'] ?? '';
            $out['questions'][$q] = ['answer' => in_array($answer, ['yes', 'no'], true) ? $answer : '', 'details' => $s($in['questions'][$q]['details'] ?? '', 500)];
        }

        return $out;
    }

    public static function save(Employee $employee, array $input): void
    {
        $employee->forceFill(['pds' => self::clean($input), 'pds_updated_at' => now()])->save();
    }
}
