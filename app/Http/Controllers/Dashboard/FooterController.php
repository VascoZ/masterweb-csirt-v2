<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Footer;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FooterController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Footer::class, 'footer');
    }

    public function index(): View
    {
        return view('dashboard.footers.index', ['footers' => Footer::latest()->get()]);
    }

    public function create(): View
    {
        return view('dashboard.footers.form', ['footer' => new Footer()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Footer::create($this->validated($request));

        return redirect()->route('dashboard.footers.index')->with('success', 'Kontak berhasil ditambahkan.');
    }

    public function edit(Footer $footer): View
    {
        return view('dashboard.footers.form', compact('footer'));
    }

    public function update(Request $request, Footer $footer): RedirectResponse
    {
        $footer->update($this->validated($request, $footer));

        return redirect()->route('dashboard.footers.index')->with('success', 'Kontak berhasil diperbarui.');
    }

    public function destroy(Footer $footer): RedirectResponse
    {
        $footer->delete();

        return redirect()->route('dashboard.footers.index')->with('success', 'Kontak berhasil dihapus.');
    }

    private function validated(Request $request, ?Footer $footer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('footers')->ignore($footer)],
            'address' => ['required', 'string', 'max:1000'],
            'telephone' => ['required', 'string', 'min:6', 'max:30', 'regex:/^[0-9\s\-\+\(\)]+$/'],
            'email' => ['required', 'email'],
            'maps' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! HtmlSanitizer::mapsUrl($value)) {
                    $fail('Masukkan kode embed Google Maps (https://www.google.com/maps/embed?...).');
                }
            }],
        ]);

        // Disimpan sebagai URL embed saja; iframe dibangun ulang saat ditampilkan.
        $data['maps'] = HtmlSanitizer::mapsUrl($data['maps']);
        $data['slug'] = Str::slug($data['name']);

        return $data;
    }
}
