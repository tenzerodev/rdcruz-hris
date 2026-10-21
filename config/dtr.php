<?php

return [
    // Max euclidean distance between face descriptors to count as a match.
    // face-api.js commonly uses 0.6; lower is stricter.
    'match_threshold' => (float) env('DTR_MATCH_THRESHOLD', 0.45),

    // A new punch within this many seconds of the previous one is rejected as a duplicate.
    'duplicate_window_seconds' => 120,

    // Snapshots older than this are deleted by `php artisan dtr:prune-snapshots`.
    'snapshot_retention_days' => (int) env('DTR_SNAPSHOT_RETENTION_DAYS', 180),

    // Defaults for the settings page (stored values in the settings table win).
    'defaults' => [
        'agency_name' => env('DTR_AGENCY_NAME', 'Your Agency Name'),
        'agency_address' => '',
        'logo_path' => '',
        'am_in' => '08:00',
        'am_out' => '12:00',
        'pm_in' => '13:00',
        'pm_out' => '17:00',
        'grace_minutes' => '0',
        // Default punches per day: four = AM and PM in/out, two = one time in and one time out.
        'punch_mode' => 'four',
        // Flexible time: arrive between flexi_start and flexi_end, then work required_minutes plus lunch.
        'flexi_start' => '07:00',
        'flexi_end' => '10:00',
        'required_minutes' => '480',
        'lunch_minutes' => '60',
        'saturday_hours' => '',
        'work_saturday' => '0',
        'half_day_absence_as_undertime' => '1',
        'default_supervisor' => '',
        'geofence_enabled' => '0',
        'geofence_lat' => '',
        'geofence_lng' => '',
        'geofence_radius' => '150',
        'hr_email' => '',                  // comma-separated; gets leave for final approval and the contract digest
        'notifications_enabled' => '1',
        // Overtime
        'ot_requires_approval' => '1',     // only count OT covered by an overtime authority
        'ot_min_minutes' => '120',         // less than this in a day can't be claimed (2 hours)
        'ot_weekend_full_week' => '1',     // weekend OT only if no leave/absence Mon–Fri of that week
        'ot_full_week_holidays' => '0',    // apply the same rule to holiday OT
        'ot_rounding' => '1',              // round each day's OT down to this many minutes (1 = exact)
        'ot_count_early_arrival' => '0',   // count time before official AM arrival on work days
        'ot_coc_enabled' => '1',           // compute compensatory overtime credits (COC)
        'ot_coc_weekday_rate' => '1',      // COC hours per OT hour on work days
        'ot_coc_restday_rate' => '1.5',    // COC hours per OT hour on weekends and holidays
        'ot_coc_monthly_cap' => '40',      // max COC hours earned per month (0 = no cap)

        // Signatory for notices (NOSI/NOSA), and employer details for tax certificates
        'agency_head_name' => '',
        'agency_head_title' => 'Head of Agency',
        'agency_tin' => '',
        'hr_head_name' => '',
        'hr_head_title' => 'Chief, Human Resource Management Division',

        // First day attendance is recorded in the HRIS; no absences are counted before it (blank = first punch).
        'attendance_start_date' => '',

        // Sign-in security
        'require_2fa_staff' => '0',        // HR, payroll, auditors and admins must use two-step verification
        'session_idle_minutes' => '30',    // log out after this many minutes without activity (0 = never)
        'lockout_attempts' => '5',         // wrong passwords before the account is locked for a while
        'lockout_minutes' => '15',
        'password_min_length' => '10',

        // Data privacy (RA 10173)
        'dpo_name' => '',
        'dpo_email' => '',
        'dpo_phone' => '',
        'privacy_extra' => '',             // agency-specific text added to the privacy notice
        'face_purge_days' => '30',         // delete face data this many days after separation

        // Backups
        'backup_enabled' => '1',           // nightly backup at 1:00 AM
        'backup_keep' => '14',             // number of backups to keep
        'backup_include_snapshots' => '0', // punch photos make backups much larger

        // Email (leave mail_host empty to use the .env settings)
        'mail_host' => '',
        'mail_port' => '587',
        'mail_encryption' => 'tls',        // tls | ssl | none
        'mail_username' => '',
        'mail_password' => '',             // stored encrypted
        'mail_from_address' => '',
        'mail_from_name' => '',
    ],
];
