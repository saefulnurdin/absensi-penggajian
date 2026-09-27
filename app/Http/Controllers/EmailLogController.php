<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = EmailLog::orderByDesc('created_at')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->paginate(20)
            ->withQueryString();

        $types = EmailLog::select('type')->distinct()->orderBy('type')->pluck('type');

        return view('email-logs.index', compact('logs', 'types'));
    }
}
