<?php

use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\DeviceApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API ESP32
|--------------------------------------------------------------------------
|
| Seluruh endpoint di bawah ini dilindungi ApiKeyMiddleware
| (header X-API-KEY). Lihat config/app.php -> api_key.
|
*/

Route::prefix('attendance')->group(function () {
    Route::post('/', [AttendanceApiController::class, 'store'])->name('api.attendance.store');
});

Route::get('/employee/lookup', [AttendanceApiController::class, 'lookup']);
Route::get('/device/status', [DeviceApiController::class, 'status']);
Route::post('/device/heartbeat', [DeviceApiController::class, 'heartbeat']);

Route::get('/ping', function () {
    return response()->json(['success' => true, 'message' => 'pong']);
});
