<?php

/*
| HR rules for a GOCC. Each employment type says what the person is entitled to here.
| - plantilla: occupies a plantilla item (position, salary grade/step, service record)
| - leave_credits: earns 1.25 VL + 1.25 SL a month and files leave against credits
| - overtime: can be credited overtime / COC
| - contract: has a contract period (start/end) and a contract rate
| - agency: belongs to an outside company/agency (service provider, DBP)
| Change these to match your agency's policies.
*/

return [
    'employment_types' => [
        'permanent' => ['label' => 'Permanent', 'group' => 'plantilla', 'plantilla' => true, 'leave_credits' => true, 'overtime' => true, 'contract' => false, 'agency' => false],
        'temporary' => ['label' => 'Temporary', 'group' => 'plantilla', 'plantilla' => true, 'leave_credits' => true, 'overtime' => true, 'contract' => false, 'agency' => false],
        'coterminous' => ['label' => 'Coterminous', 'group' => 'plantilla', 'plantilla' => true, 'leave_credits' => true, 'overtime' => true, 'contract' => false, 'agency' => false],
        'casual' => ['label' => 'Casual', 'group' => 'plantilla', 'plantilla' => false, 'leave_credits' => true, 'overtime' => true, 'contract' => true, 'agency' => false],
        'contractual' => ['label' => 'Contractual', 'group' => 'plantilla', 'plantilla' => false, 'leave_credits' => true, 'overtime' => true, 'contract' => true, 'agency' => false],
        'cos' => ['label' => 'Contract of Service', 'group' => 'cos', 'plantilla' => false, 'leave_credits' => false, 'overtime' => false, 'contract' => true, 'agency' => false],
        'job_order' => ['label' => 'Job Order', 'group' => 'cos', 'plantilla' => false, 'leave_credits' => false, 'overtime' => false, 'contract' => true, 'agency' => false],
        'service_provider' => ['label' => 'Service Provider', 'group' => 'service_provider', 'plantilla' => false, 'leave_credits' => false, 'overtime' => false, 'contract' => true, 'agency' => true],
        'dbp' => ['label' => 'DBP Personnel', 'group' => 'dbp', 'plantilla' => false, 'leave_credits' => false, 'overtime' => true, 'contract' => false, 'agency' => true],
    ],

    'groups' => [
        'plantilla' => 'Plantilla & regular staff',
        'cos' => 'Contract of Service / Job Order',
        'service_provider' => 'Service Providers',
        'dbp' => 'DBP Personnel',
    ],

    // Monthly leave credit accrual for those with leave_credits.
    'monthly_accrual' => ['VL' => 1.25, 'SL' => 1.25],

    // CSC leave types (Application for Leave, CSC Form No. 6, revised 2020).
    // charge: which balance it uses (null = no credits needed). max: days per year (null = no fixed max here).
    'leave_types' => [
        'VL' => ['name' => 'Vacation Leave', 'charge' => 'VL', 'max' => null, 'basis' => 'Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
        'FL' => ['name' => 'Mandatory/Forced Leave', 'charge' => 'VL', 'max' => 5, 'basis' => 'Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
        'SL' => ['name' => 'Sick Leave', 'charge' => 'SL', 'max' => null, 'basis' => 'Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
        'ML' => ['name' => 'Maternity Leave', 'charge' => null, 'max' => 105, 'basis' => 'R.A. No. 11210 / IRR issued by CSC, DOLE and SSS'],
        'PL' => ['name' => 'Paternity Leave', 'charge' => null, 'max' => 7, 'basis' => 'R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended'],
        'SPL' => ['name' => 'Special Privilege Leave', 'charge' => 'SPL', 'max' => 3, 'basis' => 'Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
        'SOLO' => ['name' => 'Solo Parent Leave', 'charge' => null, 'max' => 7, 'basis' => 'R.A. No. 8972 / CSC MC No. 8, s. 2004'],
        'STUDY' => ['name' => 'Study Leave', 'charge' => null, 'max' => 180, 'basis' => 'Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
        'VAWC' => ['name' => '10-Day VAWC Leave', 'charge' => null, 'max' => 10, 'basis' => 'R.A. No. 9262 / CSC MC No. 15, s. 2005'],
        'REHAB' => ['name' => 'Rehabilitation Privilege', 'charge' => null, 'max' => 180, 'basis' => 'Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292'],
        'SLBW' => ['name' => 'Special Leave Benefits for Women', 'charge' => null, 'max' => 60, 'basis' => 'R.A. No. 9710 / CSC MC No. 25, s. 2010'],
        'CALAMITY' => ['name' => 'Special Emergency (Calamity) Leave', 'charge' => null, 'max' => 5, 'basis' => 'CSC MC No. 2, s. 2012, as amended'],
        'ADOPT' => ['name' => 'Adoption Leave', 'charge' => null, 'max' => 60, 'basis' => 'R.A. No. 8552'],
        'WL' => ['name' => 'Wellness Leave', 'charge' => null, 'max' => 5, 'basis' => 'Agency policy for contract of service and service providers', 'wellness' => true],
        'CTO' => ['name' => 'Compensatory Time-Off', 'charge' => 'COC', 'max' => null, 'basis' => 'CSC-DBM Joint Circular No. 2, s. 2004'],
        'OTHERS' => ['name' => 'Others', 'charge' => null, 'max' => null, 'basis' => null],
    ],

    /*
     * Wellness leave (with pay) for contract of service and service providers, per calendar year, not carried over.
     * Started before this year: 5 days. Started January–June this year: 2 days. Started July or later: none until next year.
     * "Started" is the date first engaged (Date hired on the 201 file), or the contract start if that is blank.
     */
    'wellness' => [
        'types' => ['cos', 'service_provider'],
        'full_days' => 5,
        'new_days' => 2,
        'new_until_month' => 6,
    ],

    // Balances shown on the leave card.
    'balances' => ['VL' => 'Vacation Leave', 'SL' => 'Sick Leave', 'SPL' => 'Special Privilege Leave', 'COC' => 'Compensatory Overtime Credits (hours)'],

    // Tardiness/undertime is charged to VL: 8 hours = 1 day (CSC conversion).
    'minutes_per_day' => 480,

    // Contract expiry warning (days) for COS/JO/service providers.
    'contract_warning_days' => 30,

    // COC lapses this many months after the month it was earned (CSC-DBM JC No. 2, s. 2004, as amended — check your policy).
    'coc_validity_months' => 12,

    // Payroll inputs
    'payroll' => [
        'working_days' => 22,             // daily rate = monthly salary / 22 for LWOP and absence deductions
        'gsis_employee' => 0.09,          // GSIS personal share (plantilla, casual, contractual)
        'gsis_employer' => 0.12,
        'paid_by_agency' => ['permanent', 'temporary', 'coterminous', 'casual', 'contractual', 'cos', 'job_order'],
        'gsis_members' => ['permanent', 'temporary', 'coterminous', 'casual', 'contractual'],
        /*
         * Bonuses (DBM rules for government personnel): one month's basic salary each, for those in service
         * at least 4 months as of the cut-off and still in service on it. Year-end comes with a cash gift.
         * 13th month pay and other benefits are tax-exempt up to the yearly ceiling; the excess is taxed
         * in the year-end adjustment (BIR Form 2316).
         */
        'bonuses' => [
            'mid_year' => ['label' => 'Mid-Year Bonus', 'cutoff' => '05-15', 'min_months' => 4, 'cash_gift' => 0],
            'year_end' => ['label' => 'Year-End Bonus', 'cutoff' => '10-31', 'min_months' => 4, 'cash_gift' => 5000],
        ],
        'benefits_exemption' => 90000,
        // Loans and recurring deductions: who they are remitted to.
        'lenders' => ['gsis' => 'GSIS', 'pagibig' => 'Pag-IBIG Fund', 'coop' => 'Cooperative', 'bank' => 'Bank', 'other' => 'Other'],
        // Semi-monthly payrolls: 'split' takes half of each monthly amortization every half; 'second' takes it all on the 2nd half.
        'loan_deduction' => 'split',
        // Lowest net take-home pay allowed after deductions (General Provisions of the GAA). Payslips below it are flagged.
        'min_net_pay' => 5000,
        // Fixed monthly allowances added to every payslip of these employment types (HR can remove them on a draft).
        'allowances' => [
            ['label' => 'PERA', 'amount' => 2000, 'taxable' => false, 'types' => ['permanent', 'temporary', 'coterminous', 'casual', 'contractual']],
        ],
    ],

    // SPMS: weights per output category, and the adjectival rating scale (lowest bound → label).
    'ipcr' => [
        'weights' => ['strategic' => 0.45, 'core' => 0.45, 'support' => 0.10],
        'categories' => ['strategic' => 'Strategic Priorities', 'core' => 'Core Functions', 'support' => 'Support Functions'],
        'scale' => [[4.5, 'Outstanding'], [3.5, 'Very Satisfactory'], [2.5, 'Satisfactory'], [1.5, 'Unsatisfactory'], [0, 'Poor']],
    ],

    /*
     * Sign-in roles. "manage" areas can be changed; "view" areas are read-only. '*' = everything.
     * Any role can also be linked to an employee record, so staff still file their own leave and DTR.
     */
    'roles' => [
        'admin' => ['label' => 'System administrator', 'description' => 'Everything, including user accounts, backups and system settings.',
            'manage' => ['*'], 'view' => ['*']],
        'hr' => ['label' => 'HR officer', 'description' => 'All HR work: employees, attendance, leave, overtime, travel, IPCR, recruitment, training, administrative cases, imports and HR settings.',
            'manage' => ['people', 'attendance', 'overtime', 'leave', 'travel', 'performance', 'reports', 'imports', 'documents', 'settings', 'announcements', 'recruitment', 'learning', 'cases'],
            'view' => ['payroll', 'audit']],
        'records' => ['label' => 'Records officer', 'description' => '201 files, PDS, plantilla, organization and issued documents.',
            'manage' => ['people', 'documents', 'announcements'], 'view' => ['leave', 'travel', 'reports', 'recruitment', 'learning']],
        'payroll' => ['label' => 'Payroll officer', 'description' => 'Payroll inputs; can look at attendance, leave and overtime but not change them.',
            'manage' => ['payroll'], 'view' => ['people', 'attendance', 'overtime', 'leave', 'travel', 'reports']],
        'timekeeper' => ['label' => 'Timekeeper', 'description' => 'Attendance log, DTR, kiosks, holidays, overtime and travel / OB.',
            'manage' => ['attendance', 'overtime', 'travel'], 'view' => ['people', 'leave', 'reports']],
        'auditor' => ['label' => 'Auditor (read-only)', 'description' => 'Sees all HR records and the audit log; cannot change anything.',
            'manage' => [], 'view' => ['people', 'attendance', 'overtime', 'leave', 'travel', 'performance', 'payroll', 'reports', 'documents', 'audit', 'announcements', 'recruitment', 'learning']],
        'employee' => ['label' => 'Employee', 'description' => 'Own time record, requests and 201 file only.', 'manage' => [], 'view' => []],
    ],

    // What each permission area covers (shown on the user accounts page).
    'areas' => [
        'people' => 'Employees, 201 files, PDS, plantilla, organization',
        'attendance' => 'Attendance log, DTR, kiosks, holidays',
        'overtime' => 'Overtime authorities and reports',
        'leave' => 'Leave applications and leave cards',
        'travel' => 'Travel orders / OB',
        'performance' => 'IPCR / SPMS',
        'payroll' => 'Payroll inputs',
        'reports' => 'Workforce, tardiness and absence reports',
        'imports' => 'CSV import',
        'documents' => 'Issued documents and verification',
        'announcements' => 'Memos, office orders and announcements',
        'recruitment' => 'Vacancies, applicants and HRMPSB assessment',
        'learning' => 'Training needs, L&D plan and training hours',
        'cases' => 'Administrative cases (confidential)',
        'settings' => 'HR settings (hours, flexi, overtime rules)',
        'audit' => 'Audit log',
        'system' => 'User accounts, backups, email, security, system status',
    ],

    /*
     * CSC habitual tardiness / absenteeism thresholds.
     * Tardy: 10 or more times a month, for 2 months in a semester or 2 consecutive months.
     * Absent: unauthorized absences over 2.5 days a month, for 3 months in a semester or 3 consecutive months.
     */
    'habitual' => [
        'tardy_per_month' => 10, 'tardy_months' => 2,
        'absent_per_month' => 2.5, 'absent_months' => 3,
    ],

    /*
     * Pass slips. Official (locator) slips excuse the time out; personal slips count as undertime
     * (charged to VL like any undertime). Limits apply to personal slips. Change to your office rules.
     */
    'pass_slips' => ['personal_per_month' => 4, 'personal_max_minutes' => 120],

    /*
     * Recruitment, selection and placement (CSC ORA-OHRA / agency merit selection plan).
     * Comparative assessment criteria and their maximum points (total 100). Change to your MSP.
     * Applicant files are deleted this many months after the vacancy is filled or cancelled.
     */
    'recruitment' => [
        'criteria' => [
            'education' => ['label' => 'Education', 'max' => 20],
            'training' => ['label' => 'Training', 'max' => 10],
            'experience' => ['label' => 'Experience', 'max' => 20],
            'performance' => ['label' => 'Performance', 'max' => 20],
            'potential' => ['label' => 'Written exam / potential', 'max' => 15],
            'interview' => ['label' => 'Behavioral interview', 'max' => 15],
        ],
        'documents' => ['letter' => 'Letter of intent', 'pds' => 'Personal Data Sheet (CS Form 212) with Work Experience Sheet', 'transcript' => 'Transcript of records / diploma',
            'eligibility' => 'Certificate of eligibility / license', 'ipcr' => 'Latest performance rating (if applicable)', 'trainings' => 'Training certificates'],
        'retention_months' => 12,
    ],

    // Learning & development: yearly training hours each employee should get (shown on the hours report).
    'learning' => ['target_hours' => 40],

    // Exit clearance: offices that must sign off, in order, and what each checks.
    'clearance_offices' => [
        ['office' => 'Immediate supervisor', 'checks' => 'Work and files turned over; pending tasks endorsed'],
        ['office' => 'Property / Supply', 'checks' => 'Equipment, ID, keys and issued computers returned'],
        ['office' => 'Accounting', 'checks' => 'No unliquidated cash advances or money accountabilities'],
        ['office' => 'Records', 'checks' => 'Borrowed records and documents returned'],
        ['office' => 'IT', 'checks' => 'Accounts and agency data turned over'],
        ['office' => 'Human Resource', 'checks' => 'Leave card closed, 201 file complete, exit interview'],
    ],
    'separation_reasons' => ['Resignation', 'Retirement', 'End of contract', 'Transfer to another agency', 'Dropped from the rolls', 'Death', 'Dismissal'],

    /*
     * Several agencies on one server: each gets its own database, files and logins, chosen by the web
     * address. Off by default (one agency per installation). See DEPLOY.md, "Several agencies".
     */
    'multi_agency' => [
        'enabled' => (bool) env('HRIS_MULTI_AGENCY', false),
        'registry' => env('HRIS_AGENCIES_FILE', storage_path('app/agencies.json')),
    ],

    // Loyalty award (CSC): at 10 years of continuous service, then every 5 years.
    'loyalty' => ['first' => 10, 'every' => 5, 'first_amount' => 10000, 'next_amount' => 5000],
];
