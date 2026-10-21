<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Philippine regular holidays for any year (RA 9492 as amended). Holy Week follows Easter;
 * National Heroes Day is the last Monday of August. Eid holidays and special days are proclaimed
 * yearly, so HR adds those on the Holidays page.
 */
class PhHolidays
{
    /** @return array<string, string> date (Y-m-d) => name */
    public static function regular(int $year): array
    {
        $easter = self::easter($year);
        $heroes = CarbonImmutable::create($year, 8, 31)->modify('last monday of august '.$year);

        $days = [
            "$year-01-01" => "New Year's Day",
            $easter->subDays(3)->toDateString() => 'Maundy Thursday',
            $easter->subDays(2)->toDateString() => 'Good Friday',
            "$year-04-09" => 'Araw ng Kagitingan',
            "$year-05-01" => 'Labor Day',
            "$year-06-12" => 'Independence Day',
            $heroes->toDateString() => 'National Heroes Day',
            "$year-11-30" => 'Bonifacio Day',
            "$year-12-25" => 'Christmas Day',
            "$year-12-30" => 'Rizal Day',
        ];
        ksort($days);

        return $days;
    }

    /** Easter Sunday (Gregorian, anonymous algorithm) — no calendar extension needed. */
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
