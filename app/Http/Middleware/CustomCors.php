<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomCors
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            $response = response('', Response::HTTP_NO_CONTENT);
        } else {
            $response = $next($request);
        }

        $origin = $request->headers->get('Origin');
        $allowedOrigins = array_filter([
            env('FRONTEND_URL'),
            'http://localhost:5173',
            'https://ask-para-ai-frontend.vercel.app',
        ]);

        if ($origin && in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );
        $response->headers->set(
            'Access-Control-Allow-Headers',
            'Content-Type, Authorization, X-Requested-With, Accept, Origin'
        );
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
