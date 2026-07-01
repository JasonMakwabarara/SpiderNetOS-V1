<?php

namespace App\Services;

use App\Models\ObjectType;
use App\Models\Record;
use Illuminate\Support\Carbon;

/**
 * Predictive analytics over a pipeline object (Deals by default): a
 * probability-weighted pipeline forecast, win/conversion rates, and a revenue
 * projection fitted by ordinary least-squares linear regression on the monthly
 * won-revenue series.
 */
class ForecastService
{
    /** Default win-probability by (lower-cased) stage name. */
    protected array $probabilities = [
        'lead' => 0.10,
        'qualified' => 0.30,
        'proposal' => 0.60,
        'negotiation' => 0.80,
        'won' => 1.0,
        'closed won' => 1.0,
        'lost' => 0.0,
        'closed lost' => 0.0,
    ];

    public function forecast(string $objectSlug = 'deals', string $valueField = 'value', string $stageField = 'stage', int $periods = 3): array
    {
        $object = ObjectType::where('slug', $objectSlug)->first();
        if (! $object) {
            return ['available' => false, 'reason' => "Object '{$objectSlug}' not found."];
        }

        $deals = $object->records()->get(['id', 'data', 'created_at']);

        $pipeline = $this->pipeline($deals, $valueField, $stageField);
        $conversion = $this->conversion($deals, $stageField);
        $revenue = $this->revenueProjection($deals, $valueField, $stageField, $periods);

        return [
            'available' => true,
            'object' => $objectSlug,
            'pipeline' => $pipeline,
            'conversion' => $conversion,
            'revenue' => $revenue,
        ];
    }

    /** Probability-weighted open pipeline + already-won value. */
    protected function pipeline($deals, string $valueField, string $stageField): array
    {
        $openValue = 0.0;
        $weighted = 0.0;
        $wonValue = 0.0;
        $byStage = [];

        foreach ($deals as $deal) {
            $stage = strtolower(trim((string) ($deal->data[$stageField] ?? '')));
            $value = (float) ($deal->data[$valueField] ?? 0);
            $prob = $this->probabilities[$stage] ?? 0.2;

            $byStage[$stage] ??= ['value' => 0.0, 'weighted' => 0.0, 'count' => 0];
            $byStage[$stage]['value'] += $value;
            $byStage[$stage]['weighted'] += $value * $prob;
            $byStage[$stage]['count']++;

            if ($this->isWon($stage)) {
                $wonValue += $value;
            } elseif (! $this->isLost($stage)) {
                $openValue += $value;
                $weighted += $value * $prob;
            }
        }

        return [
            'open_value' => round($openValue, 2),
            'weighted_open' => round($weighted, 2),
            'won_value' => round($wonValue, 2),
            // Expected total = realised wins + risk-adjusted open pipeline.
            'expected_total' => round($wonValue + $weighted, 2),
            'by_stage' => $byStage,
        ];
    }

    protected function conversion($deals, string $stageField): array
    {
        $won = 0;
        $lost = 0;
        $total = 0;

        foreach ($deals as $deal) {
            $stage = strtolower(trim((string) ($deal->data[$stageField] ?? '')));
            $total++;
            if ($this->isWon($stage)) {
                $won++;
            } elseif ($this->isLost($stage)) {
                $lost++;
            }
        }

        $closed = $won + $lost;

        return [
            'won' => $won,
            'lost' => $lost,
            'total' => $total,
            // Win rate among closed deals.
            'win_rate' => $closed ? round($won / $closed, 4) : 0.0,
            // Overall conversion across the whole pipeline.
            'conversion_rate' => $total ? round($won / $total, 4) : 0.0,
        ];
    }

    /**
     * Monthly won-revenue history + a linear-regression projection for the next
     * $periods months, with a simple confidence proxy (R²).
     */
    protected function revenueProjection($deals, string $valueField, string $stageField, int $periods): array
    {
        // Build the trailing 12-month won-revenue series.
        $months = [];
        $cursor = Carbon::now()->startOfMonth()->subMonths(11);
        for ($i = 0; $i < 12; $i++) {
            $months[$cursor->copy()->addMonths($i)->format('Y-m')] = 0.0;
        }

        foreach ($deals as $deal) {
            $stage = strtolower(trim((string) ($deal->data[$stageField] ?? '')));
            if (! $this->isWon($stage)) {
                continue;
            }
            $when = $this->closeMonth($deal);
            if (array_key_exists($when, $months)) {
                $months[$when] += (float) ($deal->data[$valueField] ?? 0);
            }
        }

        $history = collect($months)->map(fn ($v, $m) => ['month' => $m, 'value' => round($v, 2)])->values()->all();

        $y = array_column($history, 'value');
        [$slope, $intercept, $r2] = $this->linearRegression($y);

        $projection = [];
        $n = count($y);
        $last = Carbon::createFromFormat('Y-m', end($history)['month'] ?? Carbon::now()->format('Y-m'))->startOfMonth();
        for ($i = 1; $i <= $periods; $i++) {
            $predicted = max(0.0, $slope * ($n - 1 + $i) + $intercept);
            $projection[] = [
                'month' => $last->copy()->addMonths($i)->format('Y-m'),
                'value' => round($predicted, 2),
                'projected' => true,
            ];
        }

        return [
            'history' => $history,
            'projection' => $projection,
            'trend' => $slope > 0 ? 'up' : ($slope < 0 ? 'down' : 'flat'),
            'monthly_growth' => round($slope, 2),
            'confidence' => round($r2, 3),
        ];
    }

    /** OLS regression on a y-series with implicit x = 0..n-1. Returns [slope, intercept, r²]. */
    protected function linearRegression(array $y): array
    {
        $n = count($y);
        if ($n < 2) {
            return [0.0, $y[0] ?? 0.0, 0.0];
        }

        $sumX = $sumY = $sumXY = $sumXX = 0.0;
        foreach ($y as $i => $value) {
            $sumX += $i;
            $sumY += $value;
            $sumXY += $i * $value;
            $sumXX += $i * $i;
        }

        $denom = ($n * $sumXX) - ($sumX * $sumX);
        if ($denom == 0.0) {
            return [0.0, $sumY / $n, 0.0];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // Coefficient of determination (R²).
        $meanY = $sumY / $n;
        $ssTot = $ssRes = 0.0;
        foreach ($y as $i => $value) {
            $predicted = $slope * $i + $intercept;
            $ssTot += ($value - $meanY) ** 2;
            $ssRes += ($value - $predicted) ** 2;
        }
        $r2 = $ssTot == 0.0 ? 0.0 : max(0.0, 1 - ($ssRes / $ssTot));

        return [$slope, $intercept, $r2];
    }

    protected function closeMonth(Record $deal): string
    {
        $close = $deal->data['close_date'] ?? null;
        if ($close) {
            try {
                return Carbon::parse($close)->format('Y-m');
            } catch (\Throwable $e) {
                // fall through to created_at
            }
        }

        return ($deal->created_at ?? Carbon::now())->format('Y-m');
    }

    protected function isWon(string $stage): bool
    {
        return str_contains($stage, 'won');
    }

    protected function isLost(string $stage): bool
    {
        return str_contains($stage, 'lost');
    }
}
