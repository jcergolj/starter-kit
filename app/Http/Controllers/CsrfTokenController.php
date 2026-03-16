<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsrfTokenController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => csrf_token()]);
    }
}
