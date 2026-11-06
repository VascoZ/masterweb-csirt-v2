<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Advisory;
use App\Models\Post;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $isAdmin = $request->user()->isAdmin();

        $ticketCounts = $isAdmin
            ? Ticket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            : collect();

        return view('dashboard.index', [
            'isAdmin' => $isAdmin,
            'ticketStats' => collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => [
                'status' => $s,
                'total' => (int) ($ticketCounts[$s->value] ?? 0),
            ]]),
            'openTickets' => $isAdmin ? Ticket::open()->latest()->take(5)->get() : collect(),
            'advisoryCount' => $isAdmin ? Advisory::count() : 0,
            'postCount' => $isAdmin ? Post::count() : $request->user()->posts()->count(),
        ]);
    }
}
