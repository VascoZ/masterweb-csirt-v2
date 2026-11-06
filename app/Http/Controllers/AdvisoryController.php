<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Models\Advisory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvisoryController extends Controller
{
    public function index(Request $request): View
    {
        $severity = Severity::tryFrom((string) $request->query('severity'));

        return view('pages.advisories.index', [
            'severity' => $severity,
            'advisories' => Advisory::publiclyVisible()
                ->when($severity, fn ($query) => $query->where('severity', $severity->value))
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = '%' . $request->string('search') . '%';
                    $query->where(fn ($q) => $q->where('title', 'like', $search)->orWhere('cve', 'like', $search)->orWhere('code', 'like', $search));
                })
                ->latest('published_at')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function show(Advisory $advisory): View
    {
        // Advisory selain TLP:CLEAR atau yang belum terbit tidak pernah tampil di publik.
        abort_unless($advisory->isPubliclyVisible(), 404);

        return view('pages.advisories.show', ['advisory' => $advisory]);
    }
}
