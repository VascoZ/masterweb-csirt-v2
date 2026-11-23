<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $from = rescue(fn () => $request->date('from'), null, false);
        $to = rescue(fn () => $request->date('to'), null, false);

        return view('dashboard.audit-logs.index', [
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'logs' => AuditLog::with('user')
                ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = '%' . $request->string('search') . '%';
                    $q->where(fn ($q) => $q->where('description', 'like', $search)->orWhere('ip_address', 'like', $search));
                })
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
