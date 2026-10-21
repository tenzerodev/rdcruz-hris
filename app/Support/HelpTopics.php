<?php

namespace App\Support;

use App\Models\User;

/**
 * Content of the in-app help (/help). Each topic: who it is for, numbered steps, tips and a screenshot
 * in public/images/help. "for": employee | supervisor | an admin permission area (people, leave, …) | system.
 */
class HelpTopics
{
    public const GROUPS = [
        'start' => 'Getting started',
        'employee' => 'For employees',
        'supervisor' => 'For supervisors',
        'hr' => 'For HR, payroll and timekeepers',
        'admin' => 'For system administrators',
    ];

    /** @return list<array{id: string, group: string, for: string, title: string, summary: string, steps: list<string>, tips?: list<string>, image?: string, link?: array{0: string, 1: string}}> */
    public static function all(): array
    {
        return [
            // Getting started
            ['id' => 'sign-in', 'group' => 'start', 'for' => 'everyone', 'title' => 'Signing in', 'image' => 'login.jpg',
                'summary' => 'Use the username and password from HR. Your first sign-in may ask you to choose your own password.',
                'steps' => [
                    'Open the HRIS address your agency gave you (on your phone or computer).',
                    'Type your **username** and **password**, then click **Log in**.',
                    'If HR set your password, you will be asked to choose a new one. Use at least 10 characters with letters and numbers.',
                    'If you turned on two-step verification, type the 6-digit code from your authenticator app.',
                    'If your account is limited to your agency-issued computer, the first sign-in on that computer asks for the activation code from HR.',
                ],
                'tips' => ['After several wrong passwords the account locks for a few minutes. Ask HR to reset your password if you forgot it.', 'You are signed out automatically after a period without activity.']],
            ['id' => 'navigation', 'group' => 'start', 'for' => 'everyone', 'title' => 'Finding your way around', 'image' => 'me-home.jpg',
                'summary' => 'The menu on the left shows only what your role can use. On a phone, open it with the ☰ button.',
                'steps' => [
                    'The **Home** page shows today\'s time-ins, this month so far, and anything that needs your attention.',
                    'Choose **Light**, **Dark** or **Auto** at the bottom of the menu.',
                    'The gear icon next to your name opens **Sign-in & security** (password and two-step verification).',
                    'HR staff who are also employees see **My time & requests** in the menu to switch to their own pages.',
                ]],
            ['id' => 'two-step', 'group' => 'start', 'for' => 'everyone', 'title' => 'Two-step verification and your password', 'image' => 'account-security.jpg',
                'summary' => 'Protect your account with a code from your phone. HR accounts may be required to use it.',
                'steps' => [
                    'Click the gear icon beside your name (or open **Sign-in & security**).',
                    'To change your password: enter the current one, then the new one twice, and click **Change password**.',
                    'To turn on two-step verification: type your password and click **Set up**.',
                    'Install Google Authenticator, Microsoft Authenticator or Authy on your phone, add an account and scan the QR code.',
                    'Type the 6-digit code the app shows and click **Turn on**.',
                    '**Save the recovery codes** shown (print or copy them). Each lets you sign in once if you lose your phone.',
                ],
                'tips' => ['Lost your phone and your recovery codes? Ask the system administrator to reset two-step verification.']],

            // Employees
            ['id' => 'time-in', 'group' => 'employee', 'for' => 'employee', 'title' => 'Time in and out with your face',
                'summary' => 'At the office kiosk, or on your own phone or laptop if HR allows it. The punch type (AM arrival, etc.) is chosen automatically.',
                'steps' => [
                    'At the office: stand in front of the kiosk and look at the camera.',
                    'On your own device: open **Time in / out** and allow the camera when the browser asks.',
                    'Keep your face inside the oval, in good light, without sunglasses or a cap.',
                    '**Blink** when asked. If the blink is not detected, **turn your head slowly left and right**.',
                    'Hold still for a moment. A green message confirms the punch and the time.',
                    'To record a different punch than the one suggested, tap the punch type (e.g. **PM Departure**) before looking at the camera.',
                ],
                'tips' => ['A punch made twice within two minutes is ignored.', 'Missed a punch? Ask HR or your timekeeper to add it with the reason.', 'The camera only works on the secure (https) address of the HRIS.']],
            ['id' => 'dtr', 'group' => 'employee', 'for' => 'employee', 'title' => 'Your DTR (CSC Form 48)', 'image' => 'form48.jpg',
                'summary' => 'Your daily time record is built from your punches, leave, travel and holidays.',
                'steps' => [
                    'On **Home**, click **My Form 48**. It opens the current month, ready to print.',
                    'Use **Print**, landscape, on A4 or legal paper. Two copies print on one page.',
                    'Sign it and have your supervisor sign as **In Charge**.',
                ],
                'tips' => ['Every printout has a QR code and verification code, so anyone can check that it is genuine at the /verify page.']],
            ['id' => 'leave', 'group' => 'employee', 'for' => 'employee', 'title' => 'Filing leave', 'image' => 'me-leave.jpg',
                'summary' => 'File leave online; your supervisor recommends it and HR approves it. Your balances are on the same page.',
                'steps' => [
                    'Open **Leave** in the menu. Your balances (VL, SL, SPL, COC — or wellness leave for COS and service providers) are at the top.',
                    'Choose the leave type, the dates (or a half day) and fill in the details asked for that type.',
                    'Click **Submit application**. Your supervisor is notified.',
                    'Follow the status in **My applications**. You can cancel it while it is pending.',
                    'Once approved, print **CSC Form 6** from the application.',
                ],
                'tips' => ['If you do not have enough credits, the excess is approved without pay.', 'COS and service providers: 5 days of wellness leave a year (2 in your first year if you started January–June).']],
            ['id' => 'overtime-travel', 'group' => 'employee', 'for' => 'employee', 'title' => 'Overtime and travel / official business', 'image' => 'me-overtime.jpg',
                'summary' => 'Ask for approval before rendering overtime or going on official business.',
                'steps' => [
                    'Open **Overtime**, enter the dates, the expected hours and the work to be done, then click **Send to supervisor**.',
                    'Open **Travel / OB** for official business or travel: type, dates (or hours for a partial day), destination and purpose, then **Send to supervisor**.',
                    'Your supervisor approves; you are emailed the result. Approved OB days count as present on your DTR.',
                ],
                'tips' => ['Overtime counts only if it is at least 2 hours in a day.', 'Weekend overtime needs a full week of attendance (no leave or absence Monday to Friday).']],
            ['id' => 'wfh', 'group' => 'employee', 'for' => 'employee', 'title' => 'Work from home and accomplishment reports', 'image' => 'me-wfh.jpg',
                'summary' => 'When HR sets a work-from-home arrangement for you, you time in from home and write what you accomplished each day.',
                'steps' => [
                    'Your schedule is under **Work from home**. On a work-from-home day, the Home page says so.',
                    'Time in and out from **Time in / out** as usual — from your agency-issued computer if the arrangement requires it.',
                    'At the end of the day, under **Write accomplishments**, pick the day, list your outputs and click **Save**.',
                    'At month end, click **Print … report**, sign it and have your supervisor review it.',
                ]],
            ['id' => 'devices', 'group' => 'employee', 'for' => 'employee', 'title' => 'Registering your agency-issued computer', 'image' => 'me-devices.jpg',
                'summary' => 'HR gives you a one-time code to register the laptop or desktop issued to you.',
                'steps' => [
                    'On the issued computer, sign in to the HRIS and open **My devices**.',
                    'Type the **activation code** from HR (e.g. ABCD-2345) and click **Register this computer**.',
                    'Keep using the same browser on that computer. Clearing its cookies removes the registration; ask HR for a new code if that happens.',
                ],
                'tips' => ['Enter the code only on the computer issued to you. HR can see which browser and internet address each code was entered from, and will cancel a registration made on another device.']],
            ['id' => 'payslips', 'group' => 'employee', 'for' => 'employee', 'title' => 'Payslips and tax certificate', 'image' => 'me-payslips.jpg',
                'summary' => 'Your payslips appear once the payroll office releases a payroll. Only you and payroll staff can see them.',
                'steps' => [
                    'Open **Payslips**. The year-to-date gross, tax and net are at the top.',
                    'Click **View / print** to see the earnings, deductions and net pay. Print or save it as PDF.',
                    'At year end, click **Tax certificate (BIR 2316 data)** for your annual compensation and tax.',
                ],
                'tips' => ['Absences and undertime come from your DTR. Questions: contact the payroll office.']],
            ['id' => 'profile', 'group' => 'employee', 'for' => 'employee', 'title' => 'Your 201 file and Personal Data Sheet', 'image' => 'me-profile.jpg',
                'summary' => 'See your 201 file and keep your PDS (CS Form 212) up to date.',
                'steps' => [
                    'Open **My 201 / PDS** to see your service record, education, eligibility and trainings.',
                    'Click **Update my PDS** to update your personal data sheet, then **Save**.',
                    'Print the PDS or your service record from the same page.',
                ]],
            ['id' => 'ipcr', 'group' => 'employee', 'for' => 'employee', 'title' => 'Performance (IPCR)', 'image' => 'me-ipcr.jpg',
                'summary' => 'Set your targets at the start of the period and report accomplishments at the end.',
                'steps' => [
                    'Open **Performance (IPCR)** and click **Start my IPCR** for the current rating period.',
                    'Click **Add output** for each target under strategic, core and support functions, then **Submit targets** to your supervisor.',
                    'At the end of the period, enter your actual accomplishments and click **Submit for rating**. Your supervisor rates them and HR finalizes.',
                ]],
            ['id' => 'privacy', 'group' => 'employee', 'for' => 'employee', 'title' => 'Your data and privacy', 'image' => 'me-privacy.jpg',
                'summary' => 'See what the HRIS keeps about you, download a copy, or withdraw consent for face recognition.',
                'steps' => [
                    'Open **My data & privacy**.',
                    'Click **Download my data** for a complete copy (JSON file).',
                    'To stop using face recognition, type your password and click **Withdraw consent**. Your face data is deleted and HR is told.',
                ]],

            // Supervisors
            ['id' => 'approvals', 'group' => 'supervisor', 'for' => 'supervisor', 'title' => 'Approving your team\'s requests',
                'summary' => 'If people report to you, the Approvals page lists their leave, overtime and travel requests.',
                'steps' => [
                    'Open **Approvals** (the number shows how many are waiting).',
                    'For leave: **Recommend approval** (it goes to HR for final approval) or **Disapprove** with a reason.',
                    'For overtime and travel: **Approve** or **Disapprove**.',
                    'Approve your team\'s IPCR targets (or **Return for revision**) and rate them at the end of the period.',
                ]],

            // HR
            ['id' => 'dashboard', 'group' => 'hr', 'for' => 'staff', 'title' => 'The HR dashboard',
                'summary' => 'Today\'s attendance, who is on leave or travel, requests waiting for action and contracts ending soon.',
                'steps' => ['Open **Dashboard**. Click any number or card to go to the list behind it.']],
            ['id' => 'employees', 'group' => 'hr', 'for' => 'people', 'title' => 'Employees and 201 files', 'image' => 'admin-employees.jpg', 'link' => ['admin.employees.index', 'Open Employees'],
                'summary' => 'Add employees of every type, keep their 201 file, and enroll their face for attendance.',
                'steps' => [
                    'Open **Employees** and click **Add employee**. Choose the employment type first; the form shows the fields that apply.',
                    'Set **Date hired / first engaged** (not a contract renewal) and, for plantilla, the **Step date**.',
                    'To give a login: fill in a username and a temporary password. They must change it at first sign-in.',
                    'Open the employee and use the tabs: Overview, 201 (education, eligibility, trainings), Service record, Documents, Leave.',
                    'Click **Enroll face**, tick the consent box after the employee reads the privacy notice, and capture their face.',
                ],
                'tips' => ['Many employees at once? Use Import data (CSV).']],
            ['id' => 'organization', 'group' => 'hr', 'for' => 'people', 'title' => 'Organizational structure', 'image' => 'admin-org.jpg', 'link' => ['admin.org.index', 'Open Organization'],
                'summary' => 'Approved and interim structures with offices, departments, divisions and units.',
                'steps' => [
                    'Open **Organization**. Create the approved structure, add units (choose the level and the unit above), set heads and assign people.',
                    'For an interim arrangement, create an **Interim structure** starting from a copy of the approved one, change it and give it dates. It applies while in effect.',
                    'Click **Update supervisors** to make each unit head the approver of the people in it.',
                ]],
            ['id' => 'attendance', 'group' => 'hr', 'for' => 'attendance', 'title' => 'Attendance log and printing DTRs', 'image' => 'admin-dtr.jpg', 'link' => ['admin.dtr.index', 'Open DTR'],
                'summary' => 'See every punch, add a missed one with the reason, and print Form 48 for everyone.',
                'steps' => [
                    'Open **Attendance log** to search punches; click **Add manual punch** for a missed time-in (the reason is kept in the audit log).',
                    'Open **DTR (Form 48)**, choose the month and office, and click **Open printable DTR**, then **Print**.',
                    'Set holidays under **Holidays**, and office hours and flexi-time under **HR settings**.',
                ]],
            ['id' => 'habitual', 'group' => 'hr', 'for' => 'reports', 'title' => 'Habitual tardiness and absenteeism', 'image' => 'admin-habitual.jpg', 'link' => ['admin.reports.habitual', 'Open the report'],
                'summary' => 'Monthly tardy counts and unauthorized absences, flagged by the CSC rules.',
                'steps' => ['Open **Tardiness & absences**, choose the year and office.', 'Red cells are over the monthly limit; the Finding column names the rule met.', 'Download the CSV for your records. Check each case against the DTR before any action.']],
            ['id' => 'overtime-admin', 'group' => 'hr', 'for' => 'overtime', 'title' => 'Overtime authorities and reports', 'image' => 'admin-overtime.jpg', 'link' => ['admin.overtime.index', 'Open Overtime'],
                'summary' => 'Approve requests, add office-order authorities and print the monthly overtime report with COC.',
                'steps' => ['Approve or disapprove waiting requests at the top.', 'Add an authority for several employees at once (dates, maximum hours, office order).', 'Open the **Overtime report** for the month, or download it as CSV.']],
            ['id' => 'wfh-admin', 'group' => 'hr', 'for' => 'attendance', 'title' => 'Work from home and issued computers', 'image' => 'admin-wfh.jpg', 'link' => ['admin.wfh.index', 'Open Work from home'],
                'summary' => 'Set who may work from home and on which days, and register the computers they may use.',
                'steps' => [
                    'Open **Issued computers**, choose the employee, enter the computer and property number, and click **Register and get code**. Give the code to the employee.',
                    'Optionally tick **sign in only from issued computers** to limit their HRIS use to that computer.',
                    'After the code is used, check the **Registered on** column to confirm it was entered on the right computer (see *Checking where a computer was registered*).',
                    'Open **Work from home**, pick employees, dates and days (leave days blank for full work from home), and save.',
                    'See who is working from home today and their accomplishment reports on the same page.',
                ]],
            ['id' => 'devices-check', 'group' => 'hr', 'for' => 'attendance', 'title' => 'Checking where a computer was registered', 'link' => ['admin.devices.index', 'Open Issued computers'],
                'summary' => 'Make sure each activation code was entered on the computer you issued, not on a phone or personal laptop.',
                'steps' => [
                    'Open **Issued computers** and look at the **Registered on** column. It shows the browser and system the code was entered on (e.g. "Chrome 141 on Windows") and the internet address (IP) at that time.',
                    'Compare it with the computer you issued. A Mac listed for an issued Windows laptop, or the warning **Phone or tablet, not a computer**, means the code was used somewhere else.',
                    'If it does not match, click **New code**. The wrong registration stops working right away. Enter the new code on the issued computer yourself.',
                    'If the code was entered at the office, the IP should be your office internet address. Ask IT for it if you are unsure.',
                    '**Last used** shows when and from which IP the computer was last used to time in.',
                ],
                'tips' => [
                    'Safest practice: HR or IT enters the code on the issued computer before handing it over, so the employee never sees the code.',
                    'The last-used IP is usually different from the registration IP during work from home; that is normal.',
                    'The browser name is reported by the browser itself. Treat it as a guide; a technical user can change it.',
                    '"IP not recorded" means the computer was registered before this check was added. Click **New code** if you want it recorded.',
                ]],
            ['id' => 'leave-admin', 'group' => 'hr', 'for' => 'leave', 'title' => 'Leave approval and leave card posting', 'image' => 'admin-leave.jpg', 'link' => ['admin.leave.index', 'Open Leave applications'],
                'summary' => 'Give final approval to recommended leave, and post the monthly credits.',
                'steps' => [
                    'Open **Leave applications**. Recommended applications wait for your **Approve** or **Disapprove**.',
                    'At month end, open **Leave card posting**, choose the month and click **Post month**. It adds 1.25 VL and SL, charges undertime to VL and credits COC. Running it again replaces that month.',
                    'In December, click **Post year-end**: forced leave, SPL lapse and next year\'s SPL.',
                ]],
            ['id' => 'monetization', 'group' => 'hr', 'for' => 'leave', 'title' => 'Leave monetization and terminal leave', 'image' => 'admin-monetization.jpg', 'link' => ['admin.leave.monetization', 'Open Monetization'],
                'summary' => 'Compute and record the money value of leave credits.',
                'steps' => [
                    'Open **Monetization & terminal leave** and choose the employee.',
                    'Regular monetization: at least 10 days of VL, keeping at least 5. Special: 50% or more with a reason.',
                    'Terminal leave: all VL and SL × the highest monthly salary × 0.0481927. Click **Record terminal leave**.',
                    'Print the computation. The days are deducted from the leave card; **Cancel** restores them.',
                ]],
            ['id' => 'payroll', 'group' => 'hr', 'for' => 'payroll', 'title' => 'Payroll, bonuses and payslips', 'image' => 'admin-payroll.jpg', 'link' => ['admin.payroll.runs.index', 'Open Payroll'],
                'summary' => 'Make a draft payroll from attendance, adjust it, then release payslips to employees.',
                'steps' => [
                    'Post the month\'s leave cards first so undertime is charged to leave before pay.',
                    'Open **Payroll & payslips**, choose the month and pay period (whole month, 1st or 2nd half), and click **Make draft payroll**.',
                    'For bonuses choose **Mid-Year Bonus** or **Year-End Bonus & Cash Gift**: eligible employees (4 months in service by the cut-off) are listed automatically.',
                    'Click **Adjust** on a payslip to add loans, honoraria or other items. Tax is recomputed.',
                    'Check the totals and click **Release to employees**. They are emailed (without amounts). **Withdraw to draft** if you find a mistake.',
                    'Download the **Register (CSV)** for accounting and the bank, or **Print all payslips**.',
                ]],
            ['id' => 'tax', 'group' => 'hr', 'for' => 'payroll', 'title' => 'Year-end tax adjustment (BIR 2316)', 'image' => 'admin-tax.jpg', 'link' => ['admin.tax.index', 'Open Annual tax'],
                'summary' => 'Annual taxable pay, tax due and tax withheld per employee, from released payslips.',
                'steps' => ['Open **Annual tax (2316)** and choose the year.', 'The last column shows what to collect or refund; add it to the December payslip with Adjust.', 'Print the 2316 data sheets and download the alphalist CSV for eBIRForms.']],
            ['id' => 'compensation', 'group' => 'hr', 'for' => 'people', 'title' => 'Step increments and salary adjustments', 'image' => 'admin-compensation.jpg', 'link' => ['admin.compensation.index', 'Open Step increments'],
                'summary' => 'Find who is due a step increment, issue NOSIs, and apply a new salary schedule with NOSAs.',
                'steps' => [
                    'Add your salary schedule under **Salary schedules** (upload the CSV template: grade, step 1 … step 8, or type the amounts).',
                    'The list shows who is due a step (3 years in the step) and anything holding it back, such as a Poor rating.',
                    'Click **Grant & issue NOSI**: the salary, 201 file and service record are updated. Print the notice.',
                    'For a new tranche, add the schedule, open it and click **Apply and issue NOSAs**.',
                ]],
            ['id' => 'clearance', 'group' => 'hr', 'for' => 'people', 'title' => 'Clearance and exit', 'image' => 'admin-clearance.jpg', 'link' => ['admin.clearances.index', 'Open Clearance'],
                'summary' => 'Track sign-offs from each office, then separate the employee in one step.',
                'steps' => [
                    'Open **Clearance & exit**, choose the employee, the last day and the reason, and click **Start clearance**.',
                    'As each office signs, type the signatory\'s name and click **Clear**.',
                    'When all have cleared, click **Complete clearance**: the employee is separated, their login and issued computers are disabled and the service record is closed.',
                    'Then compute the terminal leave benefit and print the clearance certificate.',
                ]],
            ['id' => 'csc-reports', 'group' => 'hr', 'for' => 'reports', 'title' => 'CSC reports: plantilla, RAI, loyalty, SALN', 'image' => 'admin-reports.jpg', 'link' => ['admin.reports.plantilla', 'Open CSC reports'],
                'summary' => 'Printable reports from the 201 files and service records.',
                'steps' => [
                    '**Plantilla of Personnel**: every item with its incumbent or VACANT.',
                    '**Appointments issued (RAI)**: appointments taking effect in the month (put the nature, e.g. Promotion, in the service record remarks).',
                    '**Loyalty awards**: who reaches 10, 15, 20… years this year; click **Mark awarded**.',
                    '**SALN filing**: mark each SALN as received; the list shows who has not filed by April 30.',
                ]],
            ['id' => 'import', 'group' => 'hr', 'for' => 'imports', 'title' => 'Importing data from Excel (CSV)', 'image' => 'admin-import.jpg', 'link' => ['admin.imports.index', 'Open Import data'],
                'summary' => 'Bring in employees, plantilla, leave balances, attendance and more from spreadsheets.',
                'steps' => [
                    'Open **Import data (CSV)** and choose a section. Read the column instructions and **Download the template**.',
                    'Fill it in Excel and save as CSV. Upload it.',
                    'Check the preview: problems are listed by line. Nothing is saved until you click **Import**.',
                ],
                'tips' => ['Import plantilla items first, then employees, then the rest.']],
            ['id' => 'verify', 'group' => 'hr', 'for' => 'documents', 'title' => 'Document verification', 'image' => 'verify.jpg',
                'summary' => 'Every printout has a QR code. Anyone can check it is genuine; HR can revoke a code.',
                'steps' => ['Scan the QR code or open /verify and type the code.', 'The page shows the document\'s details as issued and whether a newer version exists.', 'Under **Issued documents**, HR can revoke a code issued by mistake.']],
            ['id' => 'settings', 'group' => 'hr', 'for' => 'settings', 'title' => 'HR settings and agency logo', 'image' => 'admin-settings.jpg', 'link' => ['admin.settings.edit', 'Open HR settings'],
                'summary' => 'Agency name and logo, office hours, flexi-time, overtime rules, signatories and notifications.',
                'steps' => ['Upload the agency logo; it appears on all printouts.', 'Set office hours, flexi-time and overtime rules.', 'Enter the head of agency, HR head and agency TIN used on notices and tax certificates.']],

            // Admin
            ['id' => 'users', 'group' => 'admin', 'for' => 'system', 'title' => 'User accounts and roles', 'image' => 'admin-users.jpg', 'link' => ['admin.users.index', 'Open User accounts'],
                'summary' => 'Give HR staff the right role; reset passwords and two-step verification.',
                'steps' => [
                    'Open **User accounts** and fill in **Add an HR staff account**: name, username, role, and the employee record if they are also an employee.',
                    'Give them the temporary password shown, privately.',
                    'Change a role from the list; **Reset password**, **Reset 2-step** or **Disable** as needed.',
                ]],
            ['id' => 'backups', 'group' => 'admin', 'for' => 'system', 'title' => 'Backups and restore', 'image' => 'admin-backups.jpg', 'link' => ['admin.backups.index', 'Open Backups'],
                'summary' => 'Nightly backups of all data and files; download them and keep copies off the server.',
                'steps' => ['Click **Make a backup now** before big changes.', 'Download backups regularly to a safe place off the server.', 'To restore: choose the backup, type RESTORE and your password. A safety backup is made first.']],
            ['id' => 'system', 'group' => 'admin', 'for' => 'system', 'title' => 'System status, email and security', 'image' => 'admin-system.jpg', 'link' => ['admin.system.index', 'Open System status'],
                'summary' => 'Health checks for going live, plus email, security and privacy settings.',
                'steps' => ['Work through the health checks until each is OK.', 'Enter the mail server and click **Send test**.', 'Turn on **Require two-step verification for HR accounts**.', 'Enter the Data Protection Officer; the privacy notice updates itself.']],

            // Added modules
            ['id' => 'pass-slips', 'group' => 'employee', 'for' => 'employee', 'title' => 'Pass slips (leaving the office briefly)', 'link' => ['me.pass-slips', 'Open Pass slips'],
                'summary' => 'File a pass slip before leaving the office during work hours. Official trips are excused; personal ones count as undertime.',
                'steps' => [
                    'Open **Pass slips** and choose **Official (locator slip)** or **Personal**.',
                    'Enter when you leave and when you will be back (leave **Back by** blank if not returning), where and why, then **Send to supervisor**.',
                    'When it is approved, tap **Leaving now** as you go out and **I’m back** when you return. The actual times go to your DTR.',
                ],
                'tips' => ['Personal pass slips are limited each month. For longer absences, file leave instead.']],
            ['id' => 'memos', 'group' => 'employee', 'for' => 'employee', 'title' => 'Memos and announcements', 'link' => ['me.memos', 'Open Memos'],
                'summary' => 'New memos and office orders appear on your Home page and under Memos.',
                'steps' => ['Open the memo and read it (and the attached signed copy, if any).', 'Click **I have read and understood this**. HR sees that you acknowledged it.']],
            ['id' => 'training-me', 'group' => 'employee', 'for' => 'employee', 'title' => 'Training: your hours, nominations and requests', 'link' => ['me.learning', 'Open Training'],
                'summary' => 'See your training hours, the trainings you are nominated for, and tell HR what training would help you.',
                'steps' => ['Open **Training**.', 'Under **What would help you do your job better?** enter the skill and the training you suggest, then **Submit**.', 'Trainings you attend are added to your 201 file / PDS automatically by HR.']],
            ['id' => 'notifications', 'group' => 'start', 'for' => 'everyone', 'title' => 'Notifications', 'link' => ['notifications', 'Open Notifications'],
                'summary' => 'The bell shows requests waiting for you, decisions on your requests, released payslips, memos and reminders.',
                'steps' => ['Click **Notifications** in the menu (a number shows how many are unread).', 'Click a notice to open what it is about.', 'Untick **Also send me these by email** if you only want them in the HRIS.']],
            ['id' => 'install-app', 'group' => 'start', 'for' => 'everyone', 'title' => 'Install the HRIS on your phone',
                'summary' => 'Add the HRIS to your home screen so it opens like an app.',
                'steps' => ['Android / Chrome: open the HRIS and tap **Install the HRIS app on this device** on your Home page (or the browser menu → Install app).', 'iPhone / iPad: open the HRIS in Safari, tap **Share**, then **Add to Home Screen**.'],
                'tips' => ['The app needs an internet connection. Time-in still checks your face and location as usual.']],
            ['id' => 'office-head', 'group' => 'supervisor', 'for' => 'employee', 'title' => 'OPCR / DPCR for office and division heads', 'link' => ['me.ipcr', 'Open Performance'],
                'summary' => 'If HR made you head of an office or division rating, it appears on your Performance (IPCR) page.',
                'steps' => ['Open **Performance (IPCR)** → **Office / division ratings I head**.', 'Enter the office outputs, success indicators, budget and who is accountable, then **Save targets**. HR approves them.', 'When HR opens the rating phase, enter the actual accomplishments. HR (the Performance Management Team) rates them.']],
            ['id' => 'loans', 'group' => 'hr', 'for' => 'payroll', 'title' => 'Loans and recurring deductions', 'link' => ['admin.loans.index', 'Open Loans & deductions'],
                'summary' => 'Enter a loan once; it is deducted from every regular payroll until paid, stopped, or the last month.',
                'steps' => [
                    'Open **Loans & deductions** and fill in **Add a loan or deduction**: employee, who it is remitted to, name on the payslip, amount per month and total payable.',
                    'For contributions with no end (MP2, union dues), choose **Recurring contribution** and leave the total blank.',
                    'Make or recompute the draft payroll: the deductions are added automatically. Payments are recorded when the payroll is released, and undone if it is withdrawn.',
                    'Use **Stop** to pause a deduction; the payment history is kept.',
                ],
                'tips' => ['Semi-monthly payrolls take half of the monthly amount each pay day (changeable in config/hris.php).', 'Payslips with net pay below ₱5,000 are flagged on the payroll page.']],
            ['id' => 'remittances', 'group' => 'hr', 'for' => 'payroll', 'title' => 'Remittances and the bank file', 'link' => ['admin.remittances.index', 'Open Remittances'],
                'summary' => 'Monthly lists for GSIS, PhilHealth, Pag-IBIG, BIR and other lenders, and the bank credit list for a payroll.',
                'steps' => ['Release the month\'s payrolls first.', 'Open **Remittances**, pick the month, and download each agency\'s CSV.', 'On a payroll page, click **Bank file (CSV)** for the account numbers and net pay. Add missing account numbers on the employee record (Government numbers).']],
            ['id' => 'pass-slips-hr', 'group' => 'hr', 'for' => 'attendance', 'title' => 'Pass slips (HR / timekeeper)', 'link' => ['admin.pass-slips.index', 'Open Pass slips'],
                'summary' => 'Supervisors approve pass slips. HR approves those of employees without a supervisor and corrects times from the guard\'s logbook.',
                'steps' => ['Open **Pass slips** to see pending and approved slips.', 'Click **Times** on an approved slip to record the actual out and back times.', 'Use **Record a pass slip** to enter one filed on paper.']],
            ['id' => 'announcements', 'group' => 'hr', 'for' => 'announcements', 'title' => 'Posting memos and announcements', 'link' => ['admin.announcements.index', 'Open Memos & announcements'],
                'summary' => 'Post memos and office orders to everyone or to some offices, and see who has acknowledged them.',
                'steps' => ['Click **New**, enter the reference no., title and message, and attach the signed copy.', 'Pick offices or employee groups (none = everyone) and whether to ask for acknowledgment.', 'Click **Save & publish**. Everyone it is for is notified.', 'Open it later to see who acknowledged; download the list as CSV.']],
            ['id' => 'recruitment', 'group' => 'hr', 'for' => 'recruitment', 'title' => 'Recruitment and selection', 'link' => ['admin.recruitment.index', 'Open Recruitment'],
                'summary' => 'Publish vacancies on the careers page, receive applications online, score them with the HRMPSB and appoint.',
                'steps' => [
                    'Under **Vacant plantilla items**, click **Post** (or **New vacancy**). Enter the qualification standards and deadline, then **Open & publish**.',
                    'Applicants apply at the **Careers page** and get a reference number to check their status.',
                    'Open each applicant: set **Qualified** or **Not qualified**, then enter each HRMPSB member\'s scores.',
                    'Print the **Comparative assessment** for the appointing authority. Mark the chosen applicant **Selected**, then **Appoint**: the employee record and service record are created.',
                    'Print **CS Form 9** to request publication with the CSC.',
                ],
                'tips' => ['Files of applicants who were not hired are deleted automatically 12 months after the vacancy is filled (Data Privacy Act).']],
            ['id' => 'opcr', 'group' => 'hr', 'for' => 'performance', 'title' => 'OPCR / DPCR (Performance Management Team)', 'link' => ['admin.office-ratings.index', 'Open OPCR / DPCR'],
                'summary' => 'Office and division performance commitments and ratings, compared with the IPCRs of the office.',
                'steps' => ['Pick the rating period and add each office or division with its head.', 'The head enters targets; click **Approve targets**.', 'In the rating phase, enter Q, E, T ratings and **Submit rating**, then **Finalize**.', 'An office whose average IPCR is higher than its OPCR rating is flagged.']],
            ['id' => 'learning', 'group' => 'hr', 'for' => 'learning', 'title' => 'Training and development', 'link' => ['admin.learning.index', 'Open Training & development'],
                'summary' => 'Learning needs from employees, the yearly L&D plan, nominations and training hours.',
                'steps' => ['Check **Learning needs**: requests are grouped by training. Click **Add to plan** on a group.', 'Open a training, **Nominate** employees (they are notified).', 'After the training, tick who attended and click **Completed**: it is added to each attendee\'s 201 file.', 'See **Training hours** for who is below the yearly target, and print the L&D plan.']],
            ['id' => 'cases', 'group' => 'hr', 'for' => 'cases', 'title' => 'Administrative cases', 'link' => ['admin.cases.index', 'Open Administrative cases'],
                'summary' => 'A confidential record of each case from complaint to decision. Only HR officers and administrators can see it.',
                'steps' => ['Click **Open a case** with the respondent, offense and date filed.', 'Add each step (show-cause order, answer, hearing…) with its document and due date.', 'Add a preventive suspension if needed, then **Record the decision**.', 'Suspension days show on the DTR as SUSPENDED and are deducted from pay unless marked with pay.']],
            ['id' => 'analytics', 'group' => 'hr', 'for' => 'reports', 'title' => 'HR analytics', 'link' => ['admin.reports.analytics', 'Open HR analytics'],
                'summary' => 'Headcount, vacancy rate, turnover, leave, tardiness, ratings, age and sex, and who reaches 65 soon.',
                'steps' => ['Open **HR analytics** and pick the year.', 'Click **Print** for a copy for management.']],
            ['id' => 'multi-agency', 'group' => 'admin', 'for' => 'system', 'title' => 'Several agencies on one server',
                'summary' => 'One installation can serve several agencies, each with its own database, files and logins, picked by web address.',
                'steps' => ['Set **HRIS_MULTI_AGENCY=true** in .env.', 'Register the current agency: `php artisan hris:agency:add citem --host=hris.citem.gov.ph --existing`.', 'Add another: `php artisan hris:agency:add dti --host=hris.dti.gov.ph --name="DTI"`, then open its address to create its administrator.', 'After updates, run `php artisan hris:agency:run "migrate --force"`.']],
        ];
    }

    /** Can this user use what the topic describes? */
    public static function relevant(array $topic, User $user): bool
    {
        return match ($topic['for']) {
            'everyone' => true,
            'employee' => $user->employee !== null,
            'supervisor' => $user->employee !== null && $user->isSupervisor(),
            'staff' => $user->isStaff(),
            default => $user->canSee($topic['for']),
        };
    }

    /** Turns **bold** into <b>, after escaping. */
    public static function format(string $text): string
    {
        return preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', e($text));
    }
}
