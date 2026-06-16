<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function index()
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'ok';
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }
        
        return response()->json([
            'status' => ($dbStatus === 'ok') ? 'healthy' : 'degraded',
            'checks' => [
                'database' => $dbStatus,
                'timestamp' => time()
            ]
        ]);
    }
}
