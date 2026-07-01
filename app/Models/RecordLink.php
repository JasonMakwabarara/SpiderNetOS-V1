<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A directed association edge between two {@see Record}s, optionally produced by
 * a relationship {@see Attribute}. Powers graph-style traversal of the data model.
 */
class RecordLink extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'from_record_id', 'to_record_id', 'attribute_id'];

    public function fromRecord(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'from_record_id');
    }

    public function toRecord(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'to_record_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
