<?php

namespace App\Http\Controllers;

use App\Models\FeaturePack;

class FeaturePackController extends Controller
{
    public function index()
    {
        // Tenant isolation enforced by the FeaturePack global scope.
        return response()->json(FeaturePack::all());
    }

    public function show(FeaturePack $featurePack)
    {
        return response()->json($featurePack);
    }
}
