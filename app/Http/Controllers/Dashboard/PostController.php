<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\HtmlSanitizer;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Post::class);

        $posts = Post::query()
            ->unless($request->user()->isAdmin(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest()
            ->paginate(15);

        return view('dashboard.posts.index', compact('posts'));
    }

    public function create(): View
    {
        $this->authorize('create', Post::class);

        return view('dashboard.posts.form', ['post' => new Post(), 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $post = new Post($this->validated($request));
        $post->user_id = $request->user()->id;
        $post->save();

        return redirect()->route('dashboard.posts.index')->with('success', 'Artikel berhasil ditambahkan.');
    }

    public function show(Post $post): View
    {
        $this->authorize('view', $post);

        return view('dashboard.posts.show', compact('post'));
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        return view('dashboard.posts.form', ['post' => $post, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        // Penulis asli dipertahankan walau disunting admin.
        $post->update($this->validated($request, $post));

        return redirect()->route('dashboard.posts.index')->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }
        $post->delete();

        return redirect()->route('dashboard.posts.index')->with('success', 'Artikel berhasil dihapus.');
    }

    public function checkSlug(Request $request): JsonResponse
    {
        return response()->json(['slug' => SlugService::createSlug(Post::class, 'slug', (string) $request->query('title', ''))]);
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('posts')->ignore($post)],
            'category_id' => ['required', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'body' => ['required', 'string'],
        ]);

        if ($request->hasFile('image')) {
            if ($post?->image) {
                Storage::disk('public')->delete($post->image);
            }
            $data['image'] = $request->file('image')->store('post-image', 'public');
        } else {
            unset($data['image']);
        }

        $data['body'] = HtmlSanitizer::clean($data['body']);
        $data['excerpt'] = Str::limit(trim(html_entity_decode(strip_tags($data['body']))), 200);

        // Hanya admin yang boleh menerbitkan; penulis menyimpan sebagai draf.
        if ($request->user()->can('publish', Post::class)) {
            $data['published'] = $request->boolean('published');
            $data['published_at'] = $data['published'] ? ($post?->published_at ?? now()) : null;
        }

        return $data;
    }
}
