<?php

namespace App\Services;

use App\Jobs\ComputeAiAttributeJob;
use App\Models\Attribute;
use App\Models\ObjectType;
use App\Models\Record;
use App\Models\RecordLink;
use App\Support\RecordDataValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Centralises record writes: attribute-driven validation, persistence, and
 * materialising relationship attributes into record_links edges. Shared by the
 * REST RecordController and the Atlas assistant tools so both behave identically.
 */
class RecordWriter
{
    public function __construct(protected RecordDataValidator $validator)
    {
    }

    public function create(ObjectType $objectType, array $data): Record
    {
        $clean = $this->validator->validate($objectType, $data, partial: false);

        $record = Record::create([
            'object_type_id' => $objectType->id,
            'data' => $clean,
            'created_by' => Auth::id(),
        ]);

        $this->syncLinks($record, $objectType, $clean);
        $this->computeAiAttributes($record, $objectType);
        ActivityLogger::system($record, $record->title().' created', [
            'actor' => Auth::user()?->name,
        ], '➕');
        FlowDispatcher::onRecordEvent($record, 'created');
        AgentDispatcher::onRecordEvent($record, 'created');

        return $record;
    }

    public function update(Record $record, array $data): Record
    {
        $objectType = $record->objectType;
        $clean = $this->validator->validate($objectType, $data, partial: true);

        $before = $record->data ?? [];
        $record->data = array_merge($before, $clean);
        $record->save();

        // Capture which attributes actually changed for the timeline.
        $changed = [];
        foreach ($clean as $key => $value) {
            if (! array_key_exists($key, $before) || $before[$key] !== $value) {
                $changed[] = $key;
            }
        }

        $this->syncLinks($record, $objectType, $clean);
        $this->computeAiAttributes($record, $objectType);

        if ($changed) {
            $label = count($changed) === 1
                ? "Updated {$changed[0]}"
                : 'Updated '.count($changed).' fields: '.implode(', ', $changed);
            ActivityLogger::system($record, $label, [
                'actor' => Auth::user()?->name,
                'changed' => $changed,
            ], '✏️');
        }

        FlowDispatcher::onRecordEvent($record, 'updated');
        AgentDispatcher::onRecordEvent($record, 'updated');

        return $record;
    }

    /**
     * Queue AI-attribute computation if the object has any. Falls back to running
     * synchronously when the queue backend is unavailable (e.g. local dev without
     * Redis/worker) so record writes never fail because of it.
     */
    protected function computeAiAttributes(Record $record, ObjectType $objectType): void
    {
        $hasAi = $objectType->attributes()
            ->where('type', Attribute::TYPE_AI)
            ->exists();

        if (! $hasAi) {
            return;
        }

        try {
            ComputeAiAttributeJob::dispatch($record->id, $record->tenant_id);
        } catch (\Throwable $e) {
            Log::warning('RecordWriter: queue dispatch failed, computing AI attributes inline', [
                'record' => $record->id,
                'error' => $e->getMessage(),
            ]);

            try {
                ComputeAiAttributeJob::dispatchSync($record->id, $record->tenant_id);
            } catch (\Throwable $inner) {
                Log::error('RecordWriter: inline AI attribute computation failed', [
                    'record' => $record->id,
                    'error' => $inner->getMessage(),
                ]);
            }
        }
    }

    /**
     * Rewrite record_links edges for the relationship attributes present in the
     * submitted data, validating targets against the declared target object.
     */
    public function syncLinks(Record $record, ObjectType $objectType, array $clean): void
    {
        $relationshipAttributes = $objectType->attributes()
            ->where('type', Attribute::TYPE_RELATIONSHIP)
            ->get();

        foreach ($relationshipAttributes as $attribute) {
            if (! array_key_exists($attribute->slug, $clean)) {
                continue;
            }

            $targetIds = collect((array) $clean[$attribute->slug])
                ->filter()
                ->map(fn ($id) => (int) $id);

            if ($targetIds->isNotEmpty() && ($targetSlug = $attribute->targetObjectSlug())) {
                $target = ObjectType::where('slug', $targetSlug)->first();
                $validIds = $target
                    ? Record::where('object_type_id', $target->id)->whereIn('id', $targetIds)->pluck('id')
                    : collect();
                $targetIds = $targetIds->intersect($validIds);
            }

            DB::transaction(function () use ($record, $attribute, $targetIds) {
                RecordLink::where('from_record_id', $record->id)
                    ->where('attribute_id', $attribute->id)
                    ->delete();

                foreach ($targetIds as $toId) {
                    RecordLink::create([
                        'tenant_id' => $record->tenant_id,
                        'from_record_id' => $record->id,
                        'to_record_id' => $toId,
                        'attribute_id' => $attribute->id,
                    ]);
                }
            });
        }
    }
}
