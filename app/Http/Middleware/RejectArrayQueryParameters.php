<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak parameter kueri berbentuk array (mis. `?search[]=x`) dengan 400.
 * Endpoint yang mengharapkan nilai skalar tidak boleh menerima array, karena
 * hal itu memicu TypeError/HTTP 500 dan dapat dipakai untuk mengganggu layanan.
 */
class RejectArrayQueryParameters
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->query->all() as $value) {
            if (is_array($value)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Parameter kueri tidak valid.'], 400)
                    : response('Parameter kueri tidak valid.', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
            }
        }

        return $next($request);
    }
}
