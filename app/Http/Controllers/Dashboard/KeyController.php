<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Key;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Kunci publik PGP tim. Versi terbaru dipakai di footer & security.txt.
 */
class KeyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Key::class, 'key');
    }

    public function index(): View
    {
        return view('dashboard.keys.index', ['keys' => Key::latest()->get()]);
    }

    public function create(): View
    {
        return view('dashboard.keys.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:1024', function ($attribute, $file, $fail) {
                // Deteksi MIME untuk kunci ASCII-armored tidak konsisten antar server,
                // jadi isi file diperiksa langsung.
                if (! str_contains((string) file_get_contents($file->getRealPath()), '-----BEGIN PGP PUBLIC KEY BLOCK-----')) {
                    $fail('File harus berupa kunci publik PGP (ASCII-armored, diawali "-----BEGIN PGP PUBLIC KEY BLOCK-----").');
                }
            }],
        ]);

        Key::create([
            'name' => basename($request->file('file')->getClientOriginalName()),
            'path' => $request->file('file')->storeAs('public-key', Str::random(40) . '.asc', 'public'),
        ]);

        return redirect()->route('dashboard.keys.index')->with('success', 'Kunci publik PGP berhasil diunggah.');
    }

    public function destroy(Key $key): RedirectResponse
    {
        Storage::disk('public')->delete($key->path);
        $key->delete();

        return redirect()->route('dashboard.keys.index')->with('success', 'Kunci publik berhasil dihapus.');
    }
}
