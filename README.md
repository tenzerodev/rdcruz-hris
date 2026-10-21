# rdcruz-hris

Human Resource Information System for a GOCC: face-recognition DTR, CSC Form 48, flexi-time, leave (CSC Form 6 and leave cards), travel orders/OB, overtime with COC, 201 files and plantilla. Covers plantilla staff, Contract of Service / Job Order, service providers and DBP personnel.

Laravel 13 + MySQL/MariaDB, no build step. Face detection runs in the browser (face-api.js, bundled); matching runs on the server.

## Setup (XAMPP)

```sh
mysql -uroot -e "CREATE DATABASE rdcruz_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
composer setup                                   # install, .env, key, migrate + seed, storage:link
php artisan db:seed --class=DemoSeeder           # optional sample people and a month of attendance
composer dev
```

The seeder prints the `admin` password (or set `DTR_ADMIN_PASSWORD`). Demo logins use password `password123`: `rvillanueva` (supervisor), `jdelacruz`, `mgarcia` (COS), `ltorres` (DBP). Phones need HTTPS for the camera (tunnel or `herd secure`).

## Roles

Set under **System → User accounts**. Every role can also be linked to an employee record, so HR staff time in and file leave from the same login.

| Role | Can change | Can view only |
|---|---|---|
| System administrator | everything, plus user accounts, backups, email, security | |
| HR officer | employees, 201, attendance, leave, overtime, travel, IPCR, imports, HR settings | payroll, audit log |
| Records officer | 201 files, PDS, plantilla, organization, issued documents | leave, travel, reports |
| Payroll officer | payroll inputs | attendance, leave, overtime, travel, 201 |
| Timekeeper | attendance log, DTR, kiosks, holidays, overtime, travel | 201, leave, reports |
| Auditor | nothing | all HR records and the audit log |
| Employee | own requests and records | |

- **Supervisor**: anyone set as an employee's *Immediate supervisor*. Gets an **Approvals** page: recommends leave, approves overtime and travel/OB for direct reports.
- Permissions are in `config/hris.php` (`roles`, `areas`).

## Sign-in security

- Two-step verification with any authenticator app (TOTP), with 8 one-time recovery codes. Can be required for all HR roles.
- Lockout after repeated wrong passwords, idle sign-out, password length rule, forced password change for passwords set by HR.
- Government ID numbers and two-step secrets are encrypted in the database.
- Every sign-in, failed sign-in, change, export and backup download goes to the audit log.

## Data privacy (RA 10173)

- Public privacy notice at `/privacy`, with the DPO's details (System status → Data privacy).
- Employees: **My data & privacy** — see what is held, download it all (JSON), withdraw face consent (deletes face data and punch photos, HR is notified).
- Face data of separated employees is deleted automatically after the set number of days (`hris:purge-separated`, nightly).

## Deployment, backups and status

- First run opens a **setup wizard** (agency, logo, holidays, first administrator). See **DEPLOY.md** for the full production guide.
- **System → Backups**: nightly or on-demand backups of all data and files; download, restore (with safety backup). CLI: `hris:backup`, `hris:restore`.
- **System → System status**: health checks (debug mode, HTTPS, database, scheduler, email, backups, 2FA, kiosks). CLI: `php artisan hris:check`.
- Email server can be set in the app (no .env editing), with a test button.

## Work from home & issued computers

- **Time & attendance → Work from home**: HR sets who may work from home, the period and which days (e.g. Mon & Wed rotation), with the office order no. CSV import too. The employee is emailed.
- **Time & attendance → Issued computers**: register an agency laptop/desktop to an employee; HR gets a one-time code (valid 3 days). On that computer the employee opens **My devices** and enters the code; the browser is remembered.
- On a WFH day the employee times in with face recognition from home: no office location check, and (if the arrangement says so) only from their registered computer. Punches are marked **WFH** with the computer's name in the attendance log. Other days follow the usual rules (e.g. kiosk only).
- **Sign-in limited to issued computers** (optional, per employee): set on Issued computers, or when registering a computer or setting a WFH arrangement. Those employees can't use the HRIS from any other device; existing sessions elsewhere are signed out. The first sign-in on the computer asks for HR's activation code.
- Employees write a daily **accomplishment report**; the monthly report prints with signature lines and a verification code.

## Habitual tardiness & absenteeism

**Time & attendance → Tardiness & absences**: monthly tardy counts and unauthorized absences per employee, flagged by the CSC rules (tardy 10+ times a month for 2 months in a semester or 2 consecutive months; unauthorized absences over 2.5 days a month for 3 months in a semester or 3 consecutive months). CSV export. Absences before the hire date or after separation are not counted.

## Employment types (`config/hris.php`)

| Group | Types | Leave credits | Overtime / COC | Contract dates | Agency |
|---|---|---|---|---|---|
| Plantilla & regular | Permanent, Temporary, Coterminous, Casual, Contractual | yes | yes | casual/contractual | — |
| Contract of Service / Job Order | COS, JO | no | no | yes | — |
| Service providers | Service Provider | no | no | yes | company |
| DBP personnel | DBP | no (handled by DBP) | yes | — | DBP office |

Everyone uses the DTR. Those without credits can still file leave as "Others" so the days are excused. Edit the table in `config/hris.php` to match your policies.

## Attendance rules

- **Flexi-time** (default): time-in counted from 7:00, late after 10:00; required time-out = time-in + 8 hours + 1-hour lunch. Lunch longer than allowed counts as undertime. Employees can be switched to fixed hours.
- **Weekend work** is recorded like any day.
- Approved **leave** and **travel/OB/training/WFH** excuse the day (no absence, no undertime). A partial-day OB counts its hours as time present.
- Undertime/tardiness is charged to VL at month-end posting (8 hours = 1 day).

## Overtime rules

- Must be **approved** (employee request → supervisor, or HR authority with an office order no.), up to the approved hours per day.
- At least **2 hours** in a day to claim.
- Work days: time after the required time-out. Rest days/holidays: all hours worked, less lunch.
- **Weekend overtime is not credited if the employee was on leave or absent any work day Monday–Friday of that same week.** Holidays in that week are skipped; OB/travel counts as present.
- COC: ×1 on work days, ×1.5 on weekends/holidays, max 40 hours a month; posted to the leave card at month-end. CTO leave uses COC hours.

All of these are in **Settings**.

## Leave

- CSC leave types with legal bases; printable **CSC Form 6 (Revised 2020)**.
- Flow: employee files → supervisor recommends → HR approves. Credits are charged on approval; if short, the rest is without pay. Cancelling restores credits.
- **Leave card posting** (Leave → Leave card posting, or `php artisan hris:post-month 2026-10`): +1.25 VL/SL, undertime charges, COC. Re-running a month replaces its postings.
- **Year-end** (`php artisan hris:post-year 2026`): forced leave not taken (5 days, VL ≥ 10) deducted, SPL lapses, 3 SPL granted for next year.
- Enter beginning balances from old leave cards with **Adjustment** on the leave card.

## Wellness leave (COS and service providers)

- Leave type **WL – Wellness Leave**, with pay, for contract of service and service providers only (`config/hris.php` → `wellness`).
- Per calendar year, not carried over: **5 days** if first engaged before the year; **2 days** if engaged January–June of the year; none if engaged July or later (full 5 from next year).
- Based on **Date hired** (date first engaged; use it, not the latest contract renewal), else the contract start.
- Filing is blocked beyond the remaining days (pending applications count). Approved days are paid days in the COS payroll.

## 201 file & plantilla

Personal data, government numbers, education, eligibility, L&D, service record (printable), uploaded documents (private), certificate of employment / engagement, plantilla items with vacancies, contract expiry warnings, workforce report by office and agency.

## Payroll inputs (rdcruz-ph-payroll)

**Payroll** page, per month (post the leave cards first):

- Monthly-paid staff: monthly salary less LWOP, unauthorized absences and undertime VL can't cover (monthly ÷ 22 per day).
- COS / JO: days worked (OB/travel counts) × daily rate, less undertime hours.
- Service providers and DBP personnel: attendance only (for billing / DBP reports).
- Downloads: CSV, and JSON with a `computePay()` input per employee plus the GSIS shares.
- The register preview runs `rdcruz-ph-payroll` in the browser (copied to `public/vendor/rdcruz-ph-payroll/` — copy it again after updating the library). Plantilla use GSIS 9% instead of SSS, with the library's PhilHealth, Pag-IBIG and withholding tax. COS/JO contributions and tax option are not computed.

## Payroll runs & payslips

- **Performance & pay → Payroll & payslips**: make a draft payroll for a month or a half (1–15 / 16–end). Each paid employee gets a payslip computed from attendance: basic pay, absences/LWOP/undertime, PERA (`config/hris.php` → `payroll.allowances`), GSIS 9%, PhilHealth, Pag-IBIG and withholding tax (2026 rates, same as rdcruz-ph-payroll; `App\Services\PayCalculator`).
- **Adjust** a draft payslip to add pay (honoraria — taxable or not) and deductions (loans etc.); tax is recomputed. **Recompute from attendance** after DTR corrections keeps those items.
- **Release** to employees (they get an email without amounts); **Withdraw** takes it back to draft. Register CSV and print-all for distribution.
- Employees: **Payslips** — list with year-to-date totals, printable payslip with verification code. Payslips are visible only to the employee and staff with payroll access (not supervisors).
- COS/JO: no contributions or tax withheld automatically; add their contract's deductions with Adjust. Service providers/DBP: no payslip.

## Step increments, salary schedules, NOSI/NOSA

- **People → Step increments & salary**: salary schedules (upload CSV `grade, step1 … step8`, copy, or type in). Lists who is due a step (3 years from the 201 **Step date**, else date hired; up to step 8; held back by an Unsatisfactory/Poor final IPCR). **Grant** updates salary, step, step date and service record, and prints the NOSI.
- Open a new schedule and **Apply** to issue NOSAs to everyone whose pay changes (once per schedule).

## Bonuses and annual tax

- Payroll runs also do **Mid-Year Bonus** (cut-off May 15) and **Year-End Bonus & Cash Gift** (Oct 31; ₱5,000): one month basic for GSIS-member types with 4+ months of service. Tax-exempt up to ₱90,000/year with other benefits (`config/hris.php` → `payroll.bonuses`).
- **Annual tax (2316)**: per employee from released payslips — exempt benefits, contributions, taxable pay, tax due vs withheld (collect/refund), 2316 data sheets and alphalist CSV. Employees get their own from **Payslips**.

## Leave monetization and terminal leave

- **Leave → Monetization & terminal leave**: salary × days × 0.0481927. Regular (≥10 VL, keep 5), special (with reason), terminal (all VL+SL × highest salary). Charged to the leave card; printable; cancel restores the credits.

## Clearance and exit

- **People → Clearance & exit**: offices sign off (`config/hris.php` → `clearance_offices`); completing separates the employee, closes the service record, disables the login, issued computers and WFH. Employees see the progress on Home.

## CSC reports

- Plantilla of Personnel, Report on Appointments Issued (from service records; nature in Remarks), Loyalty awards (10 years, then every 5; mark awarded), SALN filing tracker (due April 30).

## Help

- **Help** (bottom of the menu): step-by-step instructions with screenshots, filtered to the user's role, searchable and printable. Content: `app/Support/HelpTopics.php`; screenshots: `public/images/help/`.

## SPMS / IPCR

HR opens a semestral period → employees set targets (strategic/core/support outputs and success indicators) → supervisor approves or returns → HR switches the period to *rating* → employees enter accomplishments and self-ratings → supervisor rates Q/E/T → HR finalizes. Weights (45/45/10) and the adjectival scale are in `config/hris.php`. Printable IPCR.

## PDS (CSC Form 212, Revised 2017)

Employees update their own PDS (My 201 → Update my PDS); HR can too (employee → 201 File → Edit PDS). Name, IDs, education, eligibility, L&D and this agency's service come from the 201 file. Prints 4 pages on long bond.

## Email notifications

Leave (filed → supervisor, recommended → HR, decided → employee), overtime and travel requests and decisions, IPCR steps, and a weekly contract-expiry digest to HR (`php artisan schedule:work` or a cron for `schedule:run`). Set the HR email(s) in Settings and the mail server in `.env` (`MAIL_MAILER`, `MAIL_HOST`, …). Emails go to the email on each person's 201 record. A mail failure is logged and never blocks the action.

## COC expiry

Month-end posting lapses COC still unused 12 months after the month it was earned (oldest COC is used first). Change `coc_validity_months` in `config/hris.php`.

## Organizational structure

**People → Organization.** Levels: Office → Department → Division → Unit (a unit can sit directly under a department or office).

- **Approved** structure: one active at a time; activating a new one archives the old.
- **Interim** structure: usually a copy of the approved one, changed as needed, in effect between its dates. When it ends, the approved structure applies again automatically.
- Each unit has a head and title (e.g. Division Chief, OIC) and assigned personnel. Each person's *office* on the DTR and reports follows their unit in the structure in effect today.
- **Update supervisors** sets everyone's approver to their unit head (heads report to the next unit up).
- Printable organizational chart (with verification code). CSV import for units and assignments.

## CSV import

**System → Import data (CSV)**, or the **Import CSV** button on each section. Every section has an instructions page listing each column, a **template** to download (with sample rows), and an upload → **preview** step that lists problems by spreadsheet line. Nothing is saved until you click Import; rows with problems are skipped. Sections: plantilla items, employees (all types, with supervisors and logins), organizational units and assignments, holidays, beginning leave balances, time records, past approved leave, overtime authorities, travel orders/OB, service records, education, eligibility and trainings. Dates can be `2026-10-05` or `10/05/2026`; times `08:05` or `5:30 PM`; Excel's comma or semicolon CSV both work.

## Look and feel

Side navigation grouped by task (People, Time & attendance, Leave, Performance & pay, System) with counts of items waiting for action; it becomes a slide-out menu on phones. **Light / Dark / Auto** switch at the bottom of the menu (remembered per device).

## Agency logo & document verification

- **Settings → Agency logo**: PNG/JPG/WebP up to 1 MB. Printed on every document's letterhead, and shown in the menu and sign-in page. Add the agency address in Settings too.
- Every printout (Form 48, Form 6, PDS, service record, COE, travel order, IPCR, overtime report) carries a **QR code and verification code**. Anyone can scan it or enter the code at **`/verify`** (no login) to see that it's genuine, the key facts to compare with the paper, and whether it's current, **superseded** by a newer version (e.g. a corrected DTR) or **revoked**.
- Reprinting unchanged content keeps the same code. **System → Issued documents** lists every code, how often it was checked, and lets HR revoke one.
- **Set `APP_URL` in `.env` to the address people outside can reach** (e.g. `https://hris.youragency.gov.ph`); the QR codes point there.

## Tests

```sh
php artisan test
```

## Loans, remittances and bank file

Payroll → **Loans & deductions**: each loan or recurring deduction (GSIS, Pag-IBIG, cooperative, MP2, dues) is deducted from every regular payroll until paid, stopped or its last month. Payments are recorded when a payroll is released and undone when it is withdrawn. Semi-monthly payrolls take half each pay day (`hris.payroll.loan_deduction`). Payslips below the lowest net take-home pay (`hris.payroll.min_net_pay`, ₱5,000) are flagged. Loans can be imported by CSV.

**Remittances** lists, per month, the GSIS, PhilHealth, Pag-IBIG, BIR and lender amounts from released payrolls (CSV each). A payroll's **Bank file (CSV)** lists account numbers and net pay (account no. is on the employee record, encrypted).

## Pass slips

Employees file official (locator) or personal pass slips; supervisors approve; employees tap *Leaving now* / *I'm back*. On the DTR, official time out is excused and personal time out counts as undertime. Limits: `hris.pass_slips`.

## Notifications and reminders

Every notice shows in the HRIS (bell) and is emailed when email is on and the user hasn't turned it off. `php artisan hris:reminders` (daily 07:30) reminds supervisors of requests waiting over 2 days, HR of step increments due, and employees of SALN (April) and IPCR deadlines.

## Memos and announcements

HR posts memos, office orders and circulars to everyone or some offices/groups, with the signed copy attached. Employees acknowledge them; HR sees and exports who hasn't.

## Recruitment and selection

Vacancies (from vacant plantilla items), a public **careers page** (`/careers`) with online applications and status check, HRMPSB scoring (criteria in `hris.recruitment.criteria`), comparative assessment printout, CS Form No. 9, and one-click appointment that creates the employee and service record. Files of applicants not hired are deleted 12 months after the vacancy closes.

## OPCR / DPCR

Office and division performance commitments and ratings per period. The head enters targets and accomplishments; HR (PMT) approves, rates and finalizes. Offices whose average IPCR is above the OPCR rating are flagged.

## Training and development

Employees submit learning needs; HR groups them into the yearly L&D plan, nominates employees, and on completion records the training on each attendee's 201 file. Training hours report against `hris.learning.target_hours`; printable L&D plan.

## Administrative cases

Confidential (area `cases`: HR officers and administrators). Complaint to decision with dated steps, documents and due dates. Preventive and penalty suspensions show on the DTR as SUSPENDED and are deducted from pay unless marked with pay.

## HR analytics

Reports → **HR analytics**: headcount by group and office, vacancy rate, hires/separations and turnover, leave, tardiness and absences by month, IPCR ratings, sex and age, and who reaches 65 within five years.

## Install as an app (PWA)

The HRIS can be installed on phones and computers (manifest, icons from the agency logo, service worker that caches only static files and shows an offline page). Needs HTTPS.

## Several agencies on one server

Optional (`HRIS_MULTI_AGENCY=true`): each agency gets its own database, files, cache and sessions, chosen by web address. See DEPLOY.md.
