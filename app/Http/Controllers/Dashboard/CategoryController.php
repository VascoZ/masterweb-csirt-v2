<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(): View
    {
        return view('dashboard.categories.index', ['categories' => Category::withCount('posts')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('dashboard.categories.form', ['category' => new Category()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return redirect()->route('dashboard.categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('dashboard.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('dashboard.categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Sebelumnya kategori bisa dihapus walau masih dipakai, sehingga halaman artikel error.
        if ($category->posts()->exists()) {
            return back()->with('error', 'Kategori masih dipakai oleh artikel dan tidak dapat dihapus.');
        }

        $category->delete();

        return redirect()->route('dashboard.categories.index')->with('success', 'Kategori berhasil dihapus.');
    }

    public function checkSlug(Request $request): JsonResponse
    {
        return response()->json(['slug' => SlugService::createSlug(Category::class, 'slug', (string) $request->query('title', ''))]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($category)],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('categories')->ignore($category)],
        ]);
    }
}
