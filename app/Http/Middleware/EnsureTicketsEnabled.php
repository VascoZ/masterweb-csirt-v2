<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saat superadmin menutup penerimaan aduan (Pengaturan Sistem), form aduan baru
 * menampilkan pemberitahuan dan pengiriman ditolak (503). Cek status tiket lama
 * tidak terpengaruh.
 */
class EnsureTicketsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Settings::bool('tickets_enabled')) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return response()->view('pages.tickets.closed', [], 503)->header('Retry-After', '3600');
        }

        abort(503, 'Penerimaan aduan sedang ditutup sementara.');
    }
}
