<?php

namespace App\Http\Controllers;

use App\Models\Advisory;
use App\Models\Guidance;
use App\Models\Post;
use App\Models\Service;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home', [
            'posts' => Post::published()->latest()->take(6)->get(),
            'advisories' => Advisory::publiclyVisible()->latest('published_at')->take(3)->get(),
        ]);
    }

    public function profile(): View
    {
        return view('pages.profile');
    }

    public function rfc2350(): View
    {
        return view('pages.rfc2350');
    }

    public function services(): View
    {
        return view('pages.services', ['services' => Service::oldest()->get()]);
    }

    public function guidances(): View
    {
        return view('pages.guidances', ['guidances' => Guidance::latest()->paginate(10)]);
    }

    public function contact(): View
    {
        return view('pages.contact');
    }
}
