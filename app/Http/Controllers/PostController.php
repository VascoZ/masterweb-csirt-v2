<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $title = 'Semua Artikel';

        if ($request->filled('category') && $category = Category::firstWhere('slug', $request->string('category'))) {
            $title .= ' dalam ' . $category->name;
        }

        if ($request->filled('author') && $author = User::firstWhere('username', $request->string('author'))) {
            $title .= ' oleh ' . $author->name;
        }

        return view('pages.posts.index', [
            'title' => $title,
            'posts' => Post::published()->latest()
                ->filter($request->only(['search', 'category', 'author']))
                ->paginate(7)
                ->withQueryString(),
        ]);
    }

    public function show(Post $post): View
    {
        // Sebelumnya post draf bisa dibuka siapa saja lewat slug.
        abort_unless($post->published, 404);

        return view('pages.posts.show', ['post' => $post]);
    }
}
