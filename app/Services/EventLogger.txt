<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventLogger
{
    public static function log(string $type, string $aggregateId, array $data)
    {
        DB::table('events')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'aggregate_id' => $aggregateId,
            'data' => json_encode($data),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}