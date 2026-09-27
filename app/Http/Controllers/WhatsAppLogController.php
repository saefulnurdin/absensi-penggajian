<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = WhatsAppLog::orderByDesc('created_at')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->paginate(20)
            ->withQueryString();

        $types = WhatsAppLog::select('type')->distinct()->orderBy('type')->pluck('type');

        return view('whatsapp-logs.index', compact('logs', 'types'));
    }
}