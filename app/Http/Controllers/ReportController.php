<?php

namespace App\Http\Controllers;

use App\Models\ObjectType;
use App\Services\ForecastService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reports,
        protected ForecastService $forecasts,
    ) {
    }

    public function summary()
    {
        return response()->json($this->reports->summary());
    }

    public function group(Request $request, ObjectType $objectType)
    {
        $by = $request->query('by');
        if (! $by) {
            return response()->json(['error' => 'Provide ?by=<attribute slug>.'], 422);
        }

        return response()->json([
            'object' => $objectType->slug,
            'by' => $by,
            'buckets' => $this->reports->groupBy($objectType, $by),
        ]);
    }

    public function aggregate(Request $request, ObjectType $objectType)
    {
        $by = $request->query('by');
        if (! $by) {
            return response()->json(['error' => 'Provide ?by=<attribute slug>.'], 422);
        }

        $metric = $request->query('metric', 'sum');
        $field = $request->query('field');

        return response()->json([
            'object' => $objectType->slug,
            'by' => $by,
            'metric' => $metric,
            'field' => $field,
            'buckets' => $this->reports->aggregate($objectType, $by, $metric, $field),
        ]);
    }

    public function timeseries(Request $request, ObjectType $objectType)
    {
        $days = min((int) $request->query('days', 30), 365);

        return response()->json([
            'object' => $objectType->slug,
            'days' => $days,
            'series' => $this->reports->timeseries($objectType, $days),
        ]);
    }

    public function forecast(Request $request)
    {
        $object = $request->query('object', 'deals');
        $periods = min((int) $request->query('periods', 3), 12);

        return response()->json($this->forecasts->forecast($object, 'value', 'stage', $periods));
    }
}
