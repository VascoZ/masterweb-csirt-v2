<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Dokumen RFC 2350 (PDF). Versi terbaru yang ditampilkan di situs.
 */
class FileController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(File::class, 'file');
    }

    public function index(): View
    {
        return view('dashboard.files.index', ['files' => File::latest()->get()]);
    }

    public function create(): View
    {
        return view('dashboard.files.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        File::create([
            'name' => basename($request->file('file')->getClientOriginalName()),
            'path' => $request->file('file')->store('uploads', 'public'),
        ]);

        return redirect()->route('dashboard.files.index')->with('success', 'Dokumen RFC 2350 berhasil diunggah.');
    }

    public function destroy(File $file): RedirectResponse
    {
        Storage::disk('public')->delete($file->path);
        $file->delete();

        return redirect()->route('dashboard.files.index')->with('success', 'Dokumen berhasil dihapus.');
    }
}
