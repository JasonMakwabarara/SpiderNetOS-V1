<?php

namespace App\Http\Controllers;

use App\Services\MonitoringService;

class MonitoringController extends Controller
{
    public function __construct(protected MonitoringService $monitoring)
    {
    }

    public function index()
    {
        return response()->json($this->monitoring->snapshot());
    }
}
