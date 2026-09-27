<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\KasbonController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\RecapController;
use App\Http\Controllers\RfidCardController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\WorkDayController;
use App\Http\Controllers\WhatsAppLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Master Data: Pegawai
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    // Master Data: RFID
    Route::get('/rfid-cards', [RfidCardController::class, 'index'])->name('rfid-cards.index');
    Route::post('/rfid-cards', [RfidCardController::class, 'store'])->name('rfid-cards.store');
    Route::post('/rfid-cards/{card}/assign', [RfidCardController::class, 'assign'])->name('rfid-cards.assign');
    Route::post('/rfid-cards/{card}/toggle', [RfidCardController::class, 'toggle'])->name('rfid-cards.toggle');
    Route::delete('/rfid-cards/{card}', [RfidCardController::class, 'destroy'])->name('rfid-cards.destroy');

    // Master Data: Hari Kerja
    Route::get('/work-days', [WorkDayController::class, 'index'])->name('work-days.index');
    Route::put('/work-days', [WorkDayController::class, 'update'])->name('work-days.update');
    Route::post('/work-days/overrides', [WorkDayController::class, 'storeOverride'])->name('work-days.overrides.store');
    Route::delete('/work-days/overrides/{override}', [WorkDayController::class, 'destroyOverride'])->name('work-days.overrides.destroy');

    // Master Data: Shift
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::post('/shifts/{shift}/toggle', [ShiftController::class, 'toggle'])->name('shifts.toggle');
    Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');

    // Pengaturan Sistem
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Absensi
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/create', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/{attendance}/edit', [AttendanceController::class, 'edit'])->name('attendance.edit');
    Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

    // Rekap & Kalender
    Route::get('/rekap', [RecapController::class, 'index'])->name('rekap.index');
    Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar.index');

    // Payroll
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
    Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::get('/payroll/{payroll}/slip', [PayrollController::class, 'slip'])->name('payroll.slip');
    Route::post('/payroll/{payroll}/process', [PayrollController::class, 'process'])->name('payroll.process');
    Route::post('/payroll/send-many', [PayrollController::class, 'sendMany'])->name('payroll.send-many');

    // Kasbon
    Route::get('/kasbon', [KasbonController::class, 'index'])->name('kasbon.index');
    Route::post('/kasbon', [KasbonController::class, 'store'])->name('kasbon.store');
    Route::post('/kasbon/{kasbon}/approve', [KasbonController::class, 'approve'])->name('kasbon.approve');
    Route::post('/kasbon/{kasbon}/cancel', [KasbonController::class, 'cancel'])->name('kasbon.cancel');
    Route::delete('/kasbon/{kasbon}', [KasbonController::class, 'destroy'])->name('kasbon.destroy');

    // Perangkat
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');

    // Log Email
    Route::get('/email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');

    // Log WhatsApp
    Route::get('/whatsapp-logs', [WhatsAppLogController::class, 'index'])->name('whatsapp-logs.index');
});
