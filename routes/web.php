<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\Me;
use App\Http\Controllers\MeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeRatingController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\VerifyController;
use App\Support\Agencies;
use App\Support\Install;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    if (Install::needed()) {
        return redirect()->route('setup');
    }

    return redirect(auth()->user()?->homeUrl() ?? '/login');
});

// First-run setup wizard (closes once a system administrator exists).
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup', [SetupController::class, 'store']);
});

Route::get('/privacy', [PrivacyController::class, 'show'])->name('privacy');

// Multi-agency mode: the agency's own public files (logo).
Route::get('/agency-files/{path}', function (string $path) {
    abort_unless(Agencies::current() && ! str_contains($path, '..') && Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
})->where('path', '.*')->name('agency.files');

// Installable app (PWA): manifest, icons and the offline page.
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/app-icon/{size}.png', [PwaController::class, 'icon'])->whereNumber('size')->name('pwa.icon');
Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');

// Public careers page: open vacancies and online applications.
Route::prefix('careers')->name('careers.')->group(function () {
    Route::get('/', [CareersController::class, 'index'])->name('index');
    Route::get('/thank-you', [CareersController::class, 'thanks'])->name('thanks');
    Route::match(['get', 'post'], '/status', [CareersController::class, 'status'])->middleware('throttle:20,1')->name('status');
    Route::get('/{vacancy}', [CareersController::class, 'show'])->whereNumber('vacancy')->name('show');
    Route::post('/{vacancy}', [CareersController::class, 'apply'])->whereNumber('vacancy')->middleware('throttle:5,10')->name('apply');
});

// Public document verification (QR codes on printouts point here).
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/verify', [VerifyController::class, 'form'])->name('verify.form');
    Route::get('/verify/{code}', [VerifyController::class, 'show'])->where('code', '[A-Za-z0-9-]{4,20}')->name('verify.show');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/login/two-factor', [AuthController::class, 'twoFactorForm'])->name('login.two-factor');
    Route::get('/login/device', [AuthController::class, 'deviceForm'])->name('login.device');
    Route::post('/login/device', [AuthController::class, 'device'])->middleware('throttle:10,1');
    Route::post('/login/two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/help', [HelpController::class, 'index'])->middleware('auth')->name('help');

Route::middleware('auth')->prefix('notifications')->name('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/{id}', [NotificationController::class, 'open'])->whereUuid('id')->name('.open');
    Route::post('/read-all', [NotificationController::class, 'readAll'])->name('.read-all');
    Route::post('/preferences', [NotificationController::class, 'preferences'])->name('.preferences');
});

// Every user's own password and two-step verification.
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/security', [AccountController::class, 'security'])->name('security');
    Route::put('/password', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1')->name('password');
    Route::post('/two-factor', [AccountController::class, 'enableTwoFactor'])->middleware('throttle:10,1')->name('2fa.enable');
    Route::post('/two-factor/confirm', [AccountController::class, 'confirmTwoFactor'])->middleware('throttle:10,1')->name('2fa.confirm');
    Route::post('/two-factor/cancel', [AccountController::class, 'cancelTwoFactor'])->name('2fa.cancel');
    Route::delete('/two-factor', [AccountController::class, 'disableTwoFactor'])->middleware('throttle:10,1')->name('2fa.disable');
    Route::post('/two-factor/recovery-codes', [AccountController::class, 'recoveryCodes'])->middleware('throttle:10,1')->name('2fa.recovery');
});

// Shared kiosk (one device at the entrance, recognizes anyone enrolled)
Route::middleware('kiosk')->prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'show'])->name('show');
    Route::get('/token', [KioskController::class, 'token'])->name('token');
    Route::post('/punch', [KioskController::class, 'punch'])->middleware('throttle:40,1')->name('punch');
});

// Employee's own phone or laptop (verifies against their own face only)
Route::middleware(['auth', 'role:employee'])->prefix('me')->name('me.')->group(function () {
    Route::get('/', [MeController::class, 'index'])->name('index');
    Route::get('/punch', [MeController::class, 'punchPage'])->name('punch');
    Route::post('/punch', [MeController::class, 'punch'])->middleware('throttle:15,1');
    Route::get('/dtr', [MeController::class, 'dtr'])->name('dtr');
    Route::get('/password', [MeController::class, 'passwordForm'])->name('password');
    Route::put('/password', [MeController::class, 'updatePassword']);
    Route::get('/profile', [MeController::class, 'profile'])->name('profile');

    Route::get('/leave', [Me\LeaveController::class, 'index'])->name('leave');
    Route::post('/leave', [Me\LeaveController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/leave/{leave}/cancel', [Me\LeaveController::class, 'cancel'])->name('leave.cancel');

    Route::get('/overtime', [Me\RequestController::class, 'overtime'])->name('overtime');
    Route::post('/overtime', [Me\RequestController::class, 'storeOvertime'])->middleware('throttle:20,1');
    Route::post('/overtime/{authorization}/cancel', [Me\RequestController::class, 'cancelOvertime'])->name('overtime.cancel');

    Route::get('/travel', [Me\RequestController::class, 'travel'])->name('travel');
    Route::post('/travel', [Me\RequestController::class, 'storeTravel'])->middleware('throttle:20,1');
    Route::post('/travel/{order}/cancel', [Me\RequestController::class, 'cancelTravel'])->name('travel.cancel');

    Route::get('/pass-slips', [Me\PassSlipController::class, 'index'])->name('pass-slips');
    Route::post('/pass-slips', [Me\PassSlipController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/pass-slips/{slip}/cancel', [Me\PassSlipController::class, 'cancel'])->name('pass-slips.cancel');
    Route::post('/pass-slips/{slip}/clock', [Me\PassSlipController::class, 'clock'])->middleware('throttle:20,1')->name('pass-slips.clock');

    Route::get('/memos', [Me\AnnouncementController::class, 'index'])->name('memos');
    Route::get('/memos/{announcement}', [Me\AnnouncementController::class, 'show'])->name('memos.show');
    Route::post('/memos/{announcement}/acknowledge', [Me\AnnouncementController::class, 'acknowledge'])->name('memos.acknowledge');

    Route::get('/training', [Me\LearningController::class, 'index'])->name('learning');
    Route::post('/training/needs', [Me\LearningController::class, 'storeNeed'])->middleware('throttle:20,1')->name('learning.needs');
    Route::delete('/training/needs/{need}', [Me\LearningController::class, 'destroyNeed'])->name('learning.needs.destroy');

    Route::get('/ipcr', [Me\IpcrController::class, 'index'])->name('ipcr');
    Route::post('/ipcr/start/{period}', [Me\IpcrController::class, 'start'])->name('ipcr.start');

    Route::get('/payslips', [Me\PayslipController::class, 'index'])->name('payslips');
    Route::get('/tax', [Admin\TaxController::class, 'mine'])->name('tax');

    Route::get('/wfh', [Me\WfhController::class, 'index'])->name('wfh');
    Route::post('/wfh/report', [Me\WfhController::class, 'saveReport'])->middleware('throttle:30,1')->name('wfh.report');
    Route::get('/devices', [Me\WfhController::class, 'devices'])->name('devices');
    Route::post('/devices/register', [Me\WfhController::class, 'register'])->middleware('throttle:10,1')->name('devices.register');
    Route::post('/devices/forget', [Me\WfhController::class, 'forget'])->name('devices.forget');

    Route::get('/privacy', [Me\PrivacyController::class, 'index'])->name('privacy');
    Route::get('/privacy/export', [Me\PrivacyController::class, 'export'])->middleware('throttle:5,1')->name('privacy.export');
    Route::post('/privacy/withdraw', [Me\PrivacyController::class, 'withdraw'])->middleware('throttle:5,1')->name('privacy.withdraw');

    Route::get('/profile/pds', [Me\PdsController::class, 'edit'])->name('profile.pds');
    Route::put('/profile/pds', [Me\PdsController::class, 'update']);

    Route::get('/approvals', [Me\ApprovalController::class, 'index'])->name('approvals');
    Route::post('/approvals/leave/{leave}', [Me\ApprovalController::class, 'leave'])->name('approvals.leave');
    Route::post('/approvals/overtime/{authorization}', [Me\ApprovalController::class, 'overtime'])->name('approvals.overtime');
    Route::post('/approvals/travel/{order}', [Me\ApprovalController::class, 'travel'])->name('approvals.travel');
    Route::post('/approvals/pass-slips/{slip}', [Me\ApprovalController::class, 'passSlip'])->name('approvals.pass-slip');
});

// IPCR pages are shared by the ratee, the rater and HR (checked per record).
Route::middleware('auth')->prefix('me')->name('me.')->group(function () {
    Route::get('/ipcr/{ipcr}', [Me\IpcrController::class, 'show'])->name('ipcr.show');
    Route::post('/ipcr/{ipcr}', [Me\IpcrController::class, 'save'])->name('ipcr.save');
    Route::post('/ipcr/{ipcr}/review', [Me\IpcrController::class, 'review'])->name('ipcr.review');
    Route::get('/payslips/{payslip}', [Me\PayslipController::class, 'show'])->name('payslips.show');
    Route::get('/memos/{announcement}/attachment', [Me\AnnouncementController::class, 'attachment'])->name('memos.attachment');
});

// OPCR / DPCR pages are shared by HR (Performance Management Team) and the office or division head.
Route::middleware('auth')->prefix('performance/offices')->name('performance.')->group(function () {
    Route::get('/{rating}', [OfficeRatingController::class, 'show'])->name('show');
    Route::post('/{rating}', [OfficeRatingController::class, 'save'])->name('save');
    Route::post('/{rating}/decide', [OfficeRatingController::class, 'decide'])->name('decide');
    Route::delete('/{rating}', [OfficeRatingController::class, 'destroy'])->name('destroy');
    Route::get('/{rating}/print', [OfficeRatingController::class, 'print'])->name('print');
});

// Printable forms (access checked per record: self, supervisor, HR)
Route::middleware('auth')->prefix('print')->name('print.')->group(function () {
    Route::get('/leave/{leave}', [PrintController::class, 'leaveForm'])->name('leave');
    Route::get('/service-record/{employee}', [PrintController::class, 'serviceRecord'])->name('service-record');
    Route::get('/certificate/{employee}', [PrintController::class, 'certificate'])->name('certificate');
    Route::get('/travel/{order}', [PrintController::class, 'travelOrder'])->name('travel');
    Route::get('/ipcr/{ipcr}', [PrintController::class, 'ipcr'])->name('ipcr');
    Route::get('/pds/{employee}', [PrintController::class, 'pds'])->name('pds');
    Route::get('/org/{structure}', [PrintController::class, 'orgChart'])->name('org');
    Route::get('/wfh/{employee}', [PrintController::class, 'wfhReport'])->name('wfh');
    Route::get('/clearance/{clearance}', [Admin\ClearanceController::class, 'print'])->name('clearance');
    Route::get('/documents/{document}', [Admin\RecordController::class, 'download'])->name('document');
});

Route::middleware(['auth', 'role:staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('area:people')->group(function () {
        Route::resource('employees', Admin\EmployeeController::class);
        Route::post('employees/{employee}/records/{kind}', [Admin\RecordController::class, 'store'])->name('employees.records.store');
        Route::delete('employees/{employee}/records/{kind}/{id}', [Admin\RecordController::class, 'destroy'])->name('employees.records.destroy');
        Route::post('employees/{employee}/documents', [Admin\RecordController::class, 'upload'])->name('employees.documents.store');
        Route::delete('documents/{document}', [Admin\RecordController::class, 'destroyDocument'])->name('documents.destroy');
        Route::get('employees/{employee}/pds', [Admin\PdsController::class, 'edit'])->name('employees.pds');
        Route::put('employees/{employee}/pds', [Admin\PdsController::class, 'update']);
        Route::get('employees/{employee}/enroll', [Admin\EnrollmentController::class, 'show'])->name('employees.enroll');
        Route::post('employees/{employee}/samples', [Admin\EnrollmentController::class, 'store'])->name('employees.samples.store');
        Route::delete('employees/{employee}/samples', [Admin\EnrollmentController::class, 'destroy'])->name('employees.samples.destroy');

        Route::get('org', [Admin\OrgController::class, 'index'])->name('org.index');
        Route::post('org', [Admin\OrgController::class, 'store'])->name('org.store');
        Route::get('org/{structure}', [Admin\OrgController::class, 'show'])->name('org.show');
        Route::put('org/{structure}', [Admin\OrgController::class, 'update'])->name('org.update');
        Route::delete('org/{structure}', [Admin\OrgController::class, 'destroy'])->name('org.destroy');
        Route::post('org/{structure}/units', [Admin\OrgController::class, 'storeUnit'])->name('org.units.store');
        Route::put('org/{structure}/units/{unit}', [Admin\OrgController::class, 'updateUnit'])->name('org.units.update');
        Route::delete('org/{structure}/units/{unit}', [Admin\OrgController::class, 'destroyUnit'])->name('org.units.destroy');
        Route::post('org/{structure}/units/{unit}/assign', [Admin\OrgController::class, 'assign'])->name('org.assign');
        Route::delete('org/{structure}/assignments/{assignment}', [Admin\OrgController::class, 'unassign'])->name('org.unassign');
        Route::post('org/{structure}/sync-supervisors', [Admin\OrgController::class, 'syncSupervisors'])->name('org.sync');

        Route::get('compensation', [Admin\CompensationController::class, 'index'])->name('compensation.index');
        Route::post('compensation/grant/{employee}', [Admin\CompensationController::class, 'grant'])->name('compensation.grant');
        Route::post('compensation/schedules', [Admin\CompensationController::class, 'storeSchedule'])->name('compensation.schedules.store');
        Route::get('compensation/schedules/template', [Admin\CompensationController::class, 'template'])->name('compensation.template');
        Route::get('compensation/schedules/{schedule}', [Admin\CompensationController::class, 'schedule'])->name('compensation.schedule');
        Route::put('compensation/schedules/{schedule}', [Admin\CompensationController::class, 'updateSchedule'])->name('compensation.schedules.update');
        Route::delete('compensation/schedules/{schedule}', [Admin\CompensationController::class, 'destroySchedule'])->name('compensation.schedules.destroy');
        Route::post('compensation/schedules/{schedule}/apply', [Admin\CompensationController::class, 'apply'])->name('compensation.apply');
        Route::get('compensation/notices/{notice}', [Admin\CompensationController::class, 'notice'])->name('compensation.notice');
        Route::get('compensation/notices', [Admin\CompensationController::class, 'printNotices'])->name('compensation.notices.print');

        Route::get('clearances', [Admin\ClearanceController::class, 'index'])->name('clearances.index');
        Route::post('clearances', [Admin\ClearanceController::class, 'store'])->name('clearances.store');
        Route::get('clearances/{clearance}', [Admin\ClearanceController::class, 'show'])->name('clearances.show');
        Route::post('clearances/items/{item}/sign', [Admin\ClearanceController::class, 'sign'])->name('clearances.sign');
        Route::post('clearances/items/{item}/unsign', [Admin\ClearanceController::class, 'unsign'])->name('clearances.unsign');
        Route::post('clearances/{clearance}/complete', [Admin\ClearanceController::class, 'complete'])->name('clearances.complete');
        Route::post('clearances/{clearance}/cancel', [Admin\ClearanceController::class, 'cancel'])->name('clearances.cancel');

        Route::get('positions', [Admin\PositionController::class, 'index'])->name('positions.index');
        Route::post('positions', [Admin\PositionController::class, 'store'])->name('positions.store');
        Route::put('positions/{position}', [Admin\PositionController::class, 'update'])->name('positions.update');
        Route::delete('positions/{position}', [Admin\PositionController::class, 'destroy'])->name('positions.destroy');
    });

    Route::middleware('area:leave')->group(function () {
        Route::get('employees/{employee}/leave-card', [Admin\LeaveController::class, 'card'])->name('employees.leave-card');
        Route::post('employees/{employee}/leave-card', [Admin\LeaveController::class, 'adjust'])->name('employees.leave-card.adjust');
        Route::delete('leave-card/{entry}', [Admin\LeaveController::class, 'destroyEntry'])->name('leave-card.destroy');
        Route::get('leave', [Admin\LeaveController::class, 'index'])->name('leave.index');
        Route::post('leave', [Admin\LeaveController::class, 'store'])->name('leave.store');
        Route::post('leave/{leave}/decide', [Admin\LeaveController::class, 'decide'])->name('leave.decide');
        Route::get('leave/posting', [Admin\LeaveController::class, 'posting'])->name('leave.posting');
        Route::post('leave/posting', [Admin\LeaveController::class, 'post'])->name('leave.post');
        Route::get('leave/monetization', [Admin\MonetizationController::class, 'index'])->name('leave.monetization');
        Route::post('leave/monetization', [Admin\MonetizationController::class, 'store'])->name('leave.monetization.store');
        Route::delete('leave/monetization/{monetization}', [Admin\MonetizationController::class, 'destroy'])->name('leave.monetization.destroy');
        Route::get('leave/monetization/{monetization}/print', [Admin\MonetizationController::class, 'print'])->name('leave.monetization.print');
    });

    Route::middleware('area:travel')->group(function () {
        Route::get('travel', [Admin\TravelOrderController::class, 'index'])->name('travel.index');
        Route::post('travel', [Admin\TravelOrderController::class, 'store'])->name('travel.store');
        Route::post('travel/{order}/decide', [Admin\TravelOrderController::class, 'decide'])->name('travel.decide');
    });

    Route::middleware('area:performance')->group(function () {
        Route::get('ipcr', [Admin\IpcrController::class, 'index'])->name('ipcr.index');
        Route::post('ipcr/periods', [Admin\IpcrController::class, 'storePeriod'])->name('ipcr.periods.store');
        Route::put('ipcr/periods/{period}', [Admin\IpcrController::class, 'updatePeriod'])->name('ipcr.periods.update');
        Route::post('ipcr/{ipcr}/finalize', [Admin\IpcrController::class, 'finalize'])->name('ipcr.finalize');
        Route::get('office-ratings', [OfficeRatingController::class, 'index'])->name('office-ratings.index');
        Route::post('office-ratings', [OfficeRatingController::class, 'store'])->name('office-ratings.store');
    });

    Route::middleware('area:payroll')->group(function () {
        Route::get('payroll', [Admin\PayrollController::class, 'index'])->name('payroll.index');
        Route::get('payroll/export', [Admin\PayrollController::class, 'export'])->name('payroll.export');
        Route::get('payroll/tax', [Admin\TaxController::class, 'index'])->name('tax.index');
        Route::get('payroll/tax/certificate/{employee?}', [Admin\TaxController::class, 'certificate'])->name('tax.certificate');
        Route::get('payroll/runs', [Admin\PayrollRunController::class, 'index'])->name('payroll.runs.index');
        Route::post('payroll/runs', [Admin\PayrollRunController::class, 'store'])->name('payroll.runs.store');
        Route::get('payroll/runs/{run}', [Admin\PayrollRunController::class, 'show'])->name('payroll.runs.show');
        Route::post('payroll/runs/{run}/regenerate', [Admin\PayrollRunController::class, 'regenerate'])->name('payroll.runs.regenerate');
        Route::post('payroll/runs/{run}/release', [Admin\PayrollRunController::class, 'release'])->name('payroll.runs.release');
        Route::post('payroll/runs/{run}/withdraw', [Admin\PayrollRunController::class, 'withdraw'])->name('payroll.runs.withdraw');
        Route::delete('payroll/runs/{run}', [Admin\PayrollRunController::class, 'destroy'])->name('payroll.runs.destroy');
        Route::get('payroll/runs/{run}/register', [Admin\PayrollRunController::class, 'export'])->name('payroll.runs.export');
        Route::get('payroll/runs/{run}/payslips', [Admin\PayrollRunController::class, 'print'])->name('payroll.runs.print');
        Route::get('payroll/runs/{run}/bank', [Admin\PayrollRunController::class, 'bank'])->name('payroll.runs.bank');
        Route::get('payroll/loans', [Admin\LoanController::class, 'index'])->name('loans.index');
        Route::post('payroll/loans', [Admin\LoanController::class, 'store'])->name('loans.store');
        Route::put('payroll/loans/{loan}', [Admin\LoanController::class, 'update'])->name('loans.update');
        Route::delete('payroll/loans/{loan}', [Admin\LoanController::class, 'destroy'])->name('loans.destroy');
        Route::get('payroll/remittances', [Admin\RemittanceController::class, 'index'])->name('remittances.index');
        Route::get('payroll/remittances/{section}', [Admin\RemittanceController::class, 'export'])->name('remittances.export');
        Route::get('payslips/{payslip}/edit', [Admin\PayrollRunController::class, 'editSlip'])->name('payslips.edit');
        Route::put('payslips/{payslip}', [Admin\PayrollRunController::class, 'updateSlip'])->name('payslips.update');
    });

    Route::middleware('area:reports')->group(function () {
        Route::get('reports/workforce', [Admin\DashboardController::class, 'workforce'])->name('reports.workforce');
        Route::get('reports/analytics', [Admin\DashboardController::class, 'analytics'])->name('reports.analytics');
        Route::get('reports/habitual', [Admin\HabitualController::class, 'index'])->name('reports.habitual');
        Route::get('reports/loyalty', [Admin\CscReportController::class, 'loyalty'])->name('reports.loyalty');
        Route::post('reports/loyalty/{employee}', [Admin\CscReportController::class, 'award'])->name('reports.loyalty.award');
        Route::get('reports/rai', [Admin\CscReportController::class, 'rai'])->name('reports.rai');
        Route::get('reports/plantilla', [Admin\CscReportController::class, 'plantilla'])->name('reports.plantilla');
        Route::get('reports/saln', [Admin\CscReportController::class, 'saln'])->name('reports.saln');
        Route::post('reports/saln/{employee}', [Admin\CscReportController::class, 'recordSaln'])->name('reports.saln.record');
    });

    Route::middleware('area:attendance')->group(function () {
        Route::get('punches', [Admin\PunchController::class, 'index'])->name('punches.index');
        Route::get('punches/create', [Admin\PunchController::class, 'create'])->name('punches.create');
        Route::post('punches', [Admin\PunchController::class, 'store'])->name('punches.store');
        Route::get('punches/export', [Admin\PunchController::class, 'export'])->name('punches.export');
        Route::get('punches/{punch}/edit', [Admin\PunchController::class, 'edit'])->name('punches.edit');
        Route::put('punches/{punch}', [Admin\PunchController::class, 'update'])->name('punches.update');
        Route::delete('punches/{punch}', [Admin\PunchController::class, 'destroy'])->name('punches.destroy');
        Route::get('punches/{punch}/snapshot', [Admin\PunchController::class, 'snapshot'])->name('punches.snapshot');

        Route::get('dtr', [Admin\DtrController::class, 'index'])->name('dtr.index');
        Route::get('dtr/print', [Admin\DtrController::class, 'print'])->name('dtr.print');

        Route::get('kiosks', [Admin\KioskController::class, 'index'])->name('kiosks.index');
        Route::post('kiosks', [Admin\KioskController::class, 'store'])->name('kiosks.store');
        Route::put('kiosks/{kiosk}', [Admin\KioskController::class, 'update'])->name('kiosks.update');
        Route::delete('kiosks/{kiosk}', [Admin\KioskController::class, 'destroy'])->name('kiosks.destroy');
        Route::post('kiosks/{kiosk}/activate', [Admin\KioskController::class, 'activate'])->name('kiosks.activate');

        Route::get('wfh', [Admin\WfhController::class, 'index'])->name('wfh.index');
        Route::post('wfh', [Admin\WfhController::class, 'store'])->name('wfh.store');
        Route::post('wfh/{arrangement}/end', [Admin\WfhController::class, 'cancel'])->name('wfh.end');
        Route::get('devices', [Admin\WfhController::class, 'devices'])->name('devices.index');
        Route::post('devices', [Admin\WfhController::class, 'storeDevice'])->name('devices.store');
        Route::post('devices/{device}/code', [Admin\WfhController::class, 'newCode'])->name('devices.code');
        Route::post('devices/{device}/toggle', [Admin\WfhController::class, 'toggleDevice'])->name('devices.toggle');
        Route::delete('devices/{device}', [Admin\WfhController::class, 'destroyDevice'])->name('devices.destroy');
        Route::post('employees/{employee}/login-device', [Admin\WfhController::class, 'restrictLogin'])->name('devices.restrict');

        Route::get('pass-slips', [Admin\PassSlipController::class, 'index'])->name('pass-slips.index');
        Route::post('pass-slips', [Admin\PassSlipController::class, 'store'])->name('pass-slips.store');
        Route::post('pass-slips/{slip}/decide', [Admin\PassSlipController::class, 'decide'])->name('pass-slips.decide');
        Route::put('pass-slips/{slip}/times', [Admin\PassSlipController::class, 'times'])->name('pass-slips.times');

        Route::get('holidays', [Admin\HolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [Admin\HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('holidays/{holiday}', [Admin\HolidayController::class, 'destroy'])->name('holidays.destroy');
    });

    Route::middleware('area:overtime')->group(function () {
        Route::get('overtime', [Admin\OvertimeController::class, 'index'])->name('overtime.index');
        Route::post('overtime', [Admin\OvertimeController::class, 'store'])->name('overtime.store');
        Route::delete('overtime/{authorization}', [Admin\OvertimeController::class, 'destroy'])->name('overtime.destroy');
        Route::post('overtime/{authorization}/decide', [Admin\OvertimeController::class, 'decide'])->name('overtime.decide');
        Route::get('overtime/report', [Admin\OvertimeController::class, 'report'])->name('overtime.report');
    });

    Route::middleware('area:settings')->group(function () {
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/logo', [Admin\SettingController::class, 'logo'])->name('settings.logo');
    });

    Route::middleware('area:announcements')->group(function () {
        Route::resource('announcements', Admin\AnnouncementController::class)->except('show');
        Route::get('announcements/{announcement}', [Admin\AnnouncementController::class, 'show'])->name('announcements.show');
        Route::post('announcements/{announcement}/publish', [Admin\AnnouncementController::class, 'publish'])->name('announcements.publish');
        Route::post('announcements/{announcement}/unpublish', [Admin\AnnouncementController::class, 'unpublish'])->name('announcements.unpublish');
    });

    Route::middleware('area:learning')->prefix('learning')->name('learning.')->group(function () {
        Route::get('/', [Admin\LearningController::class, 'index'])->name('index');
        Route::post('/', [Admin\LearningController::class, 'store'])->name('store');
        Route::get('/plan', [Admin\LearningController::class, 'print'])->name('print');
        Route::get('/{program}', [Admin\LearningController::class, 'show'])->name('show');
        Route::put('/{program}', [Admin\LearningController::class, 'update'])->name('update');
        Route::delete('/{program}', [Admin\LearningController::class, 'destroy'])->name('destroy');
        Route::post('/{program}/nominate', [Admin\LearningController::class, 'nominate'])->name('nominate');
        Route::post('/{program}/complete', [Admin\LearningController::class, 'complete'])->name('complete');
        Route::delete('/participants/{participant}', [Admin\LearningController::class, 'removeParticipant'])->name('participants.destroy');
    });

    Route::middleware('area:cases')->prefix('cases')->name('cases.')->group(function () {
        Route::get('/', [Admin\CaseController::class, 'index'])->name('index');
        Route::post('/', [Admin\CaseController::class, 'store'])->name('store');
        Route::get('/{case}', [Admin\CaseController::class, 'show'])->name('show');
        Route::put('/{case}', [Admin\CaseController::class, 'update'])->name('update');
        Route::get('/{case}/print', [Admin\CaseController::class, 'print'])->name('print');
        Route::post('/{case}/events', [Admin\CaseController::class, 'storeEvent'])->name('events.store');
        Route::post('/{case}/decide', [Admin\CaseController::class, 'decide'])->name('decide');
        Route::post('/{case}/suspensions', [Admin\CaseController::class, 'storeSuspension'])->name('suspensions.store');
        Route::put('/suspensions/{suspension}', [Admin\CaseController::class, 'updateSuspension'])->name('suspensions.update');
        Route::delete('/suspensions/{suspension}', [Admin\CaseController::class, 'destroySuspension'])->name('suspensions.destroy');
        Route::delete('/events/{event}', [Admin\CaseController::class, 'destroyEvent'])->name('events.destroy');
        Route::get('/events/{event}/document', [Admin\CaseController::class, 'document'])->name('events.document');
    });

    Route::middleware('area:recruitment')->prefix('recruitment')->name('recruitment.')->group(function () {
        Route::get('/', [Admin\RecruitmentController::class, 'index'])->name('index');
        Route::get('/new', [Admin\RecruitmentController::class, 'create'])->name('create');
        Route::post('/', [Admin\RecruitmentController::class, 'store'])->name('store');
        Route::get('/publication', [Admin\RecruitmentController::class, 'publication'])->name('publication');
        Route::get('/{vacancy}', [Admin\RecruitmentController::class, 'show'])->name('show');
        Route::get('/{vacancy}/edit', [Admin\RecruitmentController::class, 'edit'])->name('edit');
        Route::put('/{vacancy}', [Admin\RecruitmentController::class, 'update'])->name('update');
        Route::post('/{vacancy}/status', [Admin\RecruitmentController::class, 'setStatus'])->name('status');
        Route::post('/{vacancy}/applicants', [Admin\RecruitmentController::class, 'storeApplicant'])->name('applicants.store');
        Route::get('/{vacancy}/comparative', [Admin\RecruitmentController::class, 'comparative'])->name('comparative');
        Route::post('/{vacancy}/purge-files', [Admin\RecruitmentController::class, 'purgeFiles'])->name('purge');
        Route::get('/applicants/{applicant}', [Admin\RecruitmentController::class, 'applicant'])->name('applicant');
        Route::put('/applicants/{applicant}', [Admin\RecruitmentController::class, 'updateApplicant'])->name('applicant.update');
        Route::post('/applicants/{applicant}/assess', [Admin\RecruitmentController::class, 'assess'])->name('assess');
        Route::delete('/assessments/{assessment}', [Admin\RecruitmentController::class, 'destroyAssessment'])->name('assessment.destroy');
        Route::get('/applicants/{applicant}/documents/{index}', [Admin\RecruitmentController::class, 'document'])->whereNumber('index')->name('document');
        Route::post('/applicants/{applicant}/appoint', [Admin\RecruitmentController::class, 'appoint'])->name('appoint');
    });

    Route::middleware('area:audit')->get('audit', [Admin\DashboardController::class, 'audit'])->name('audit');

    Route::middleware('area:documents')->group(function () {
        Route::get('documents', [Admin\DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents/{document}/revoke', [Admin\DocumentController::class, 'revoke'])->name('documents.revoke');
    });

    Route::middleware('area:imports')->group(function () {
        Route::get('import', [Admin\ImportController::class, 'index'])->name('imports.index');
        Route::get('import/{key}', [Admin\ImportController::class, 'show'])->name('imports.show');
        Route::get('import/{key}/template', [Admin\ImportController::class, 'template'])->name('imports.template');
        Route::post('import/{key}', [Admin\ImportController::class, 'upload'])->middleware('throttle:20,1')->name('imports.upload');
        Route::get('import/{key}/preview/{token}', [Admin\ImportController::class, 'preview'])->name('imports.preview');
        Route::post('import/{key}/preview/{token}', [Admin\ImportController::class, 'run'])->name('imports.run');
        Route::delete('import/{key}/preview/{token}', [Admin\ImportController::class, 'cancel'])->name('imports.cancel');
    });

    // System administrators only
    Route::middleware('area:system')->group(function () {
        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/reset-two-factor', [Admin\UserController::class, 'resetTwoFactor'])->name('users.reset-2fa');
        Route::post('users/{user}/toggle', [Admin\UserController::class, 'toggle'])->name('users.toggle');

        Route::get('system', [Admin\SystemController::class, 'index'])->name('system.index');
        Route::put('system/security', [Admin\SystemController::class, 'security'])->name('system.security');
        Route::put('system/mail', [Admin\SystemController::class, 'mail'])->name('system.mail');
        Route::post('system/mail/test', [Admin\SystemController::class, 'testMail'])->middleware('throttle:5,1')->name('system.mail.test');
        Route::put('system/privacy', [Admin\SystemController::class, 'privacy'])->name('system.privacy');
        Route::put('system/backups', [Admin\SystemController::class, 'backups'])->name('system.backups');

        Route::get('backups', [Admin\BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [Admin\BackupController::class, 'store'])->middleware('throttle:6,1')->name('backups.store');
        Route::post('backups/restore', [Admin\BackupController::class, 'restore'])->middleware('throttle:10,1')->name('backups.restore');
        Route::get('backups/{name}', [Admin\BackupController::class, 'download'])->name('backups.download');
        Route::delete('backups/{name}', [Admin\BackupController::class, 'destroy'])->name('backups.destroy');
    });
});
