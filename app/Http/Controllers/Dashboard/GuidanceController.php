<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Guidance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuidanceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Guidance::class, 'guidance');
    }

    public function index(): View
    {
        return view('dashboard.guidances.index', ['guidances' => Guidance::latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('dashboard.guidances.form', ['guidance' => new Guidance()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Guidance::create($data + $this->storeFile($request));

        return redirect()->route('dashboard.guidances.index')->with('success', 'Panduan berhasil diunggah.');
    }

    public function edit(Guidance $guidance): View
    {
        return view('dashboard.guidances.form', compact('guidance'));
    }

    public function update(Request $request, Guidance $guidance): RedirectResponse
    {
        $data = $this->validated($request, $guidance);

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($guidance->path);
            $data += $this->storeFile($request);
        }

        $guidance->update($data);

        return redirect()->route('dashboard.guidances.index')->with('success', 'Panduan berhasil diperbarui.');
    }

    public function destroy(Guidance $guidance): RedirectResponse
    {
        Storage::disk('public')->delete($guidance->path);
        $guidance->delete();

        return redirect()->route('dashboard.guidances.index')->with('success', 'Panduan berhasil dihapus.');
    }

    private function validated(Request $request, ?Guidance $guidance = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('guidances')->ignore($guidance)],
            'file' => [$guidance ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        return ['name' => $data['name'], 'slug' => Str::slug($data['name'])];
    }

    private function storeFile(Request $request): array
    {
        $file = $request->file('file');

        return [
            'file_name' => basename($file->getClientOriginalName()),
            'size' => $file->getSize(),
            'path' => $file->store('uploads-guidances', 'public'),
        ];
    }
}
