<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\ObjectType;
use App\Models\Record;
use Illuminate\Support\Carbon;

/**
 * Tenant-scoped reporting aggregates for the analytics dashboard and the kanban
 * board. Aggregation is performed in PHP over the JSON `data` column so it stays
 * portable across SQLite (dev) and PostgreSQL (prod).
 */
class ReportService
{
    /** Workspace headline counters. */
    public function summary(): array
    {
        $objects = ObjectType::withCount('records')->get()
            ->map(fn (ObjectType $o) => [
                'slug' => $o->slug,
                'name' => $o->name,
                'icon' => $o->icon,
                'records' => $o->records_count,
            ])->values();

        $runsByStatus = FlowRun::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'objects' => $objects,
            'totals' => [
                'records' => Record::count(),
                'activities' => Activity::count(),
                'agents' => Agent::count(),
                'flows' => Flow::count(),
                'flow_runs' => FlowRun::count(),
            ],
            'flow_runs_by_status' => [
                'success' => (int) ($runsByStatus['success'] ?? 0),
                'failed' => (int) ($runsByStatus['failed'] ?? 0),
                'running' => (int) ($runsByStatus['running'] ?? 0),
            ],
        ];
    }

    /**
     * Count records grouped by the value of a (select-like) attribute.
     * Returns one bucket per value in declared option order when available.
     */
    public function groupBy(ObjectType $object, string $attrSlug): array
    {
        $options = $this->attributeOptions($object, $attrSlug);
        $counts = [];

        foreach ($this->records($object) as $rec) {
            $value = $this->stringValue($rec->data[$attrSlug] ?? null);
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $this->orderBuckets($counts, $options)
            ->map(fn ($count, $label) => ['label' => $label, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Aggregate a numeric field grouped by another attribute.
     *
     * @param  string  $metric  sum|avg|count
     */
    public function aggregate(ObjectType $object, string $byAttr, string $metric = 'sum', ?string $field = null): array
    {
        $options = $this->attributeOptions($object, $byAttr);
        $buckets = [];

        foreach ($this->records($object) as $rec) {
            $key = $this->stringValue($rec->data[$byAttr] ?? null);
            $buckets[$key] ??= ['sum' => 0.0, 'count' => 0];
            $buckets[$key]['count']++;
            if ($field !== null) {
                $buckets[$key]['sum'] += (float) ($rec->data[$field] ?? 0);
            }
        }

        $ordered = $this->orderBuckets($buckets, $options);

        return $ordered->map(function ($agg, $label) use ($metric) {
            $value = match ($metric) {
                'avg' => $agg['count'] ? round($agg['sum'] / $agg['count'], 2) : 0,
                'count' => $agg['count'],
                default => round($agg['sum'], 2),
            };

            return ['label' => $label, 'value' => $value, 'count' => $agg['count']];
        })->values()->all();
    }

    /** Records created per day for the last N days. */
    public function timeseries(ObjectType $object, int $days = 30): array
    {
        $since = Carbon::today()->subDays($days - 1);
        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $buckets[$since->copy()->addDays($i)->toDateString()] = 0;
        }

        $object->records()
            ->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->each(function ($r) use (&$buckets) {
                $key = $r->created_at->toDateString();
                if (array_key_exists($key, $buckets)) {
                    $buckets[$key]++;
                }
            });

        return collect($buckets)->map(fn ($count, $date) => ['date' => $date, 'count' => $count])->values()->all();
    }

    // ---- Helpers ----------------------------------------------------------

    /** @return \Illuminate\Support\Collection<int, Record> */
    protected function records(ObjectType $object)
    {
        return $object->records()->get(['id', 'data']);
    }

    protected function attributeOptions(ObjectType $object, string $slug): array
    {
        $attr = $object->attributes()->where('slug', $slug)->first();

        return $attr?->config['options'] ?? [];
    }

    protected function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            return $value === [] ? '—' : implode(', ', $value);
        }

        return ($value === null || $value === '') ? '—' : (string) $value;
    }

    /**
     * Order buckets by the attribute's declared options, then any extra values,
     * keeping the empty "—" bucket last.
     */
    protected function orderBuckets(array $buckets, array $options)
    {
        $ordered = collect();
        foreach ($options as $opt) {
            if (array_key_exists($opt, $buckets)) {
                $ordered[$opt] = $buckets[$opt];
                unset($buckets[$opt]);
            }
        }
        $empty = $buckets['—'] ?? null;
        unset($buckets['—']);
        foreach ($buckets as $k => $v) {
            $ordered[$k] = $v;
        }
        if ($empty !== null) {
            $ordered['—'] = $empty;
        }

        return $ordered;
    }
}
