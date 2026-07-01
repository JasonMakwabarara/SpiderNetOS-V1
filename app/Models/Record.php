<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single row of an {@see ObjectType}. Attribute values are stored in the JSON
 * `data` column keyed by attribute slug. Relationships are also materialised as
 * {@see RecordLink} edges so the data model behaves like a graph.
 *
 * @property array|null $data
 */
class Record extends Model
{
    use BelongsToTenant;

    protected $table = 'records';

    protected $fillable = ['tenant_id', 'object_type_id', 'data', 'created_by'];

    protected $casts = ['data' => 'array'];

    public function objectType(): BelongsTo
    {
        return $this->belongsTo(ObjectType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Outgoing association edges (this record -> others). */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(RecordLink::class, 'from_record_id');
    }

    /** Incoming association edges (others -> this record). */
    public function incomingLinks(): HasMany
    {
        return $this->hasMany(RecordLink::class, 'to_record_id');
    }

    /** Timeline entries (notes, calls, system events, …). */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** Best-effort human title derived from the object's title attribute. */
    public function title(): string
    {
        $attr = $this->objectType?->titleAttribute();
        $value = $attr ? ($this->data[$attr->slug] ?? null) : null;

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        return $value !== null && $value !== '' ? (string) $value : "#{$this->id}";
    }
}
