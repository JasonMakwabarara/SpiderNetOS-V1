<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\ObjectType;
use App\Models\Record;
use App\Services\EventLogger;
use App\Services\RecordWriter;
use Illuminate\Http\Request;

/**
 * Generic CRUD over records of any object type, with attribute-driven validation,
 * JSON-path filtering/sorting, and relationship edge syncing.
 */
class RecordController extends Controller
{
    public function __construct(protected RecordWriter $writer)
    {
    }

    public function index(Request $request, ObjectType $objectType)
    {
        $query = Record::query()->where('object_type_id', $objectType->id);

        // filter[slug]=value -> exact match on a JSON attribute.
        foreach ((array) $request->input('filter', []) as $slug => $value) {
            $query->where("data->{$slug}", $value);
        }

        // Free-text search across text-like attributes.
        if ($q = $request->query('q')) {
            $textAttributes = $objectType->attributes()
                ->whereIn('type', [Attribute::TYPE_TEXT, Attribute::TYPE_EMAIL, Attribute::TYPE_URL])
                ->pluck('slug');

            $query->where(function ($sub) use ($textAttributes, $q) {
                foreach ($textAttributes as $slug) {
                    $sub->orWhere("data->{$slug}", 'like', '%'.$q.'%');
                }
            });
        }

        // sort=slug or sort=-slug (descending). Built-in columns allowed too.
        $sort = $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortKey = ltrim($sort, '-');
        if (in_array($sortKey, ['created_at', 'updated_at', 'id'], true)) {
            $query->orderBy($sortKey, $direction);
        } else {
            $query->orderBy("data->{$sortKey}", $direction);
        }

        $perPage = min((int) $request->query('per_page', 50), 200);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request, ObjectType $objectType)
    {
        $request->validate(['data' => 'required|array']);

        $record = $this->writer->create($objectType, $request->input('data'));
        EventLogger::log('record.created', (string) $record->id, ['object' => $objectType->slug]);

        return response()->json($record, 201);
    }

    public function show(Record $record)
    {
        $record->load(['objectType', 'outgoingLinks.toRecord', 'incomingLinks.fromRecord']);

        return response()->json($record);
    }

    public function update(Request $request, Record $record)
    {
        $request->validate(['data' => 'required|array']);

        $record = $this->writer->update($record, $request->input('data'));
        EventLogger::log('record.updated', (string) $record->id, ['object' => $record->objectType?->slug]);

        return response()->json($record);
    }

    public function destroy(Record $record)
    {
        EventLogger::log('record.deleted', (string) $record->id, ['object' => $record->objectType?->slug]);
        \App\Services\FlowDispatcher::onRecordEvent($record, 'deleted');
        \App\Services\AgentDispatcher::onRecordEvent($record, 'deleted');
        $record->delete();

        return response()->json(null, 204);
    }
}
