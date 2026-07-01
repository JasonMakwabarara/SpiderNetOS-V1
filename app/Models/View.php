<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved presentation of an object's records — a table (with chosen columns)
 * or a kanban board (grouped by a select attribute). Inspired by Attio's views.
 */
class View extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'object_type_id', 'name', 'slug', 'type', 'config',
    ];

    protected $casts = ['config' => 'array'];

    public const TYPE_TABLE = 'table';
    public const TYPE_KANBAN = 'kanban';

    public function objectType(): BelongsTo
    {
        return $this->belongsTo(ObjectType::class);
    }
}
