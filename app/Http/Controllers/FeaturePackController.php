<?php

namespace App\Http\Controllers;

use App\Models\FeaturePack;
use Illuminate\Http\Request;

class FeaturePackController extends Controller
{
    public function index(Request $request)
    {
        $packs = FeaturePack::where('tenant_id', $request->header('X-Tenant-ID'))->get();
        return response()->json($packs);
    }

    public function show(FeaturePack $featurePack)
    {
        return response()->json($featurePack);
    }
}
