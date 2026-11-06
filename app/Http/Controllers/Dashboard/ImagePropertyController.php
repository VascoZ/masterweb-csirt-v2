<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ImageProperty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ImagePropertyController extends Controller
{
    public const TYPES = ['Logo', 'Banner'];

    public function __construct()
    {
        $this->authorizeResource(ImageProperty::class, 'property');
    }

    public function index(): View
    {
        return view('dashboard.properties.index', ['properties' => ImageProperty::latest()->get()]);
    }

    public function create(): View
    {
        return view('dashboard.properties.form', ['property' => new ImageProperty(), 'types' => self::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image'] = $request->file('image')->store('image-property', 'public');

        ImageProperty::create($data);

        return redirect()->route('dashboard.properties.index')->with('success', 'Gambar berhasil ditambahkan.');
    }

    public function edit(ImageProperty $property): View
    {
        return view('dashboard.properties.form', ['property' => $property, 'types' => self::TYPES]);
    }

    public function update(Request $request, ImageProperty $property): RedirectResponse
    {
        $data = $this->validated($request, $property);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($property->image);
            $data['image'] = $request->file('image')->store('image-property', 'public');
        } else {
            unset($data['image']);
        }

        $property->update($data);

        return redirect()->route('dashboard.properties.index')->with('success', 'Gambar berhasil diperbarui.');
    }

    public function destroy(ImageProperty $property): RedirectResponse
    {
        Storage::disk('public')->delete($property->image);
        $property->delete();

        return redirect()->route('dashboard.properties.index')->with('success', 'Gambar berhasil dihapus.');
    }

    private function validated(Request $request, ?ImageProperty $property = null): array
    {
        $data = $request->validate([
            'property' => ['required', Rule::in(self::TYPES)],
            'name' => ['required', 'string', 'max:255', Rule::unique('image_properties')->ignore($property)],
            // SVG tidak diizinkan karena dapat memuat script.
            'image' => [$property ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        return $data;
    }
}
