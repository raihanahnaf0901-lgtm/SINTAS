<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LearningData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, LearningData $learning): JsonResponse
    {
        return response()->json(['data' => $learning->dashboard($request->user())]);
    }
}
