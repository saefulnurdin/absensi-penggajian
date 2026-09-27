<?php

namespace App\Http\Controllers;

use App\Models\DeviceLog;
use App\Services\NotificationService;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function __construct(private NotificationService $notifier) {}

    public function index(): View
    {
        $devices = DeviceLog::orderByDesc('updated_at')->get();

        // Tandai offline bila lebih dari 5 menit tidak heartbeat.
        foreach ($devices as $device) {
            if ($device->last_seen_at && $device->last_seen_at->lt(now()->subMinutes(5))) {
                if ($device->online) {
                    $device->update(['online' => false]);
                }
            }
            $device->online = $device->online && $device->last_seen_at?->gt(now()->subMinutes(5)) ?? false;
        }

        return view('devices.index', compact('devices'));
    }
}
