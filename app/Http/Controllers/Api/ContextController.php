<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ContextController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'context' => 'required|string|min:10',
        ]);

        return response()->json([
            'received_context' => $request->context,
            'note' => 'AI will be integrated here next'
        ]);
    }
}
