<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Record;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    private const ICONS = [
        'note' => '📝', 'call' => '📞', 'email' => '📧', 'meeting' => '📅', 'task' => '✅',
    ];

    /** Timeline for a single record (newest first). */
    public function index(Record $record)
    {
        $activities = $record->activities()
            ->with('user:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json($activities);
    }

    /** Add a user-authored entry to a record's timeline. */
    public function store(Request $request, Record $record)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(Activity::USER_TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $type = $validated['type'];
        $title = $validated['title'] ?: ucfirst($type);

        $activity = ActivityLogger::log($record, $type, $title, [
            'body' => $validated['body'] ?? null,
            'icon' => self::ICONS[$type] ?? '🗒️',
            'is_system' => false,
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        return response()->json($activity->load('user:id,name'), 201);
    }

    public function destroy(Activity $activity)
    {
        // Preserve the auto-captured audit trail; only user entries are removable.
        if ($activity->is_system) {
            return response()->json(['error' => 'System activities cannot be deleted.'], 403);
        }

        $activity->delete();

        return response()->json(null, 204);
    }

    /** Workspace-wide recent activity feed. */
    public function recent(Request $request)
    {
        $limit = min((int) $request->query('limit', 50), 200);

        $activities = Activity::query()
            ->with(['user:id,name', 'record:id,object_type_id'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json($activities);
    }
}
