<?php

namespace App\Imports;

/** All CSV imports, in the order HR should usually load them. */
class Registry
{
    public const IMPORTERS = [
        PositionImporter::class, EmployeeImporter::class, OrgUnitImporter::class, OrgAssignmentImporter::class, HolidayImporter::class, LeaveBalanceImporter::class,
        AttendanceImporter::class, LeaveHistoryImporter::class, OvertimeImporter::class, TravelImporter::class, WfhImporter::class,
        ServiceRecordImporter::class, EducationImporter::class, EligibilityImporter::class, TrainingImporter::class, LoanImporter::class,
    ];

    /** @return array<string, Importer> */
    public static function all(): array
    {
        $out = [];
        foreach (self::IMPORTERS as $class) {
            $i = new $class;
            $out[$i->key()] = $i;
        }

        return $out;
    }

    public static function get(string $key): Importer
    {
        return self::all()[$key] ?? abort(404);
    }
}
