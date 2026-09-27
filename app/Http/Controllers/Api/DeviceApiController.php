<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceLog;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceApiController extends Controller
{
    public function __construct(private NotificationService $notifier) {}

    /**
     * Status perangkat.
     */
    public function status(): JsonResponse
    {
        $devices = DeviceLog::orderByDesc('updated_at')->get();
        $latest = $devices->first();

        return response()->json([
            'success' => true,
            'online' => (bool) $latest?->online,
            'last_seen_at' => $latest?->last_seen_at?->toDateTimeString(),
            'device_name' => $latest?->device_name,
        ]);
    }

    /**
     * Heartbeat berkala dari ESP32 (misal tiap 30 detik).
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_name' => ['nullable', 'string', 'max:60'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'ip' => ['nullable', 'string', 'max:45'],
            'power_source' => ['nullable', 'in:MAINS,BATTERY'],
            'battery_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'wifi_rssi' => ['nullable', 'integer', 'between:-150,0'],
        ]);

        $device = DeviceLog::firstOrCreate([], [
            'device_name' => 'ESP32-Absensi-1',
        ]);

        $ip = $data['ip_address'] ?? $data['ip'] ?? $request->ip();

        $device->update([
            'device_name' => $data['device_name'] ?? $device->device_name ?? 'ESP32-Absensi-1',
            'ip_address' => $ip,
            'last_seen_at' => now(),
            'online' => true,
            'power_source' => $data['power_source'] ?? $device->power_source,
            'battery_percent' => $data['battery_percent'] ?? $device->battery_percent,
            'wifi_rssi' => $data['wifi_rssi'] ?? $device->wifi_rssi,
        ]);

        return response()->json(['success' => true, 'message' => 'Heartbeat diterima']);
    }
}
