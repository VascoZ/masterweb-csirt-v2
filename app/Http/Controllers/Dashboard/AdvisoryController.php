<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\Severity;
use App\Enums\Tlp;
use App\Http\Controllers\Controller;
use App\Models\Advisory;
use App\Support\HtmlSanitizer;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdvisoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Advisory::class, 'advisory');
    }

    public function index(Request $request): View
    {
        $tlp = Tlp::tryFrom((string) $request->query('tlp'));

        return view('dashboard.advisories.index', [
            'tlp' => $tlp,
            'advisories' => Advisory::query()
                ->when($tlp, fn ($q) => $q->where('tlp', $tlp->value))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.advisories.form', ['advisory' => new Advisory(['tlp' => Tlp::Clear, 'severity' => Severity::Medium])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $advisory = new Advisory($this->validated($request));
        $advisory->user_id = $request->user()->id;
        $advisory->save();

        return redirect()->route('dashboard.advisories.show', $advisory)->with('success', 'Advisory ' . $advisory->code . ' berhasil dibuat.');
    }

    public function show(Advisory $advisory): View
    {
        return view('dashboard.advisories.show', compact('advisory'));
    }

    public function edit(Advisory $advisory): View
    {
        return view('dashboard.advisories.form', compact('advisory'));
    }

    public function update(Request $request, Advisory $advisory): RedirectResponse
    {
        $advisory->update($this->validated($request, $advisory));

        return redirect()->route('dashboard.advisories.show', $advisory)->with('success', 'Advisory berhasil diperbarui.');
    }

    public function destroy(Advisory $advisory): RedirectResponse
    {
        $advisory->delete();

        return redirect()->route('dashboard.advisories.index')->with('success', 'Advisory berhasil dihapus.');
    }

    public function checkSlug(Request $request): JsonResponse
    {
        return response()->json(['slug' => SlugService::createSlug(Advisory::class, 'slug', (string) $request->query('title', ''))]);
    }

    private function validated(Request $request, ?Advisory $advisory = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('advisories')->ignore($advisory)],
            'tlp' => ['required', Rule::enum(Tlp::class)],
            'severity' => ['required', Rule::enum(Severity::class)],
            'cve' => ['nullable', 'string', 'max:255', 'regex:/^(CVE-\d{4}-\d{4,}[\s,;]*)*$/i'],
            'affected_products' => ['nullable', 'string', 'max:5000'],
            'summary' => ['required', 'string', 'max:1000'],
            'body' => ['required', 'string'],
            'recommendation' => ['nullable', 'string'],
            'references' => ['nullable', 'string', 'max:5000'],
        ], ['cve.regex' => 'Format CVE harus CVE-YYYY-NNNN, dipisah koma.']);

        $data['cve'] = filled($data['cve'] ?? null) ? strtoupper($data['cve']) : null;
        $data['body'] = HtmlSanitizer::clean($data['body']);
        $data['recommendation'] = HtmlSanitizer::clean($data['recommendation'] ?? null) ?: null;
        $data['published'] = $request->boolean('published');

        return $data;
    }
}
