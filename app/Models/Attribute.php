<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A typed field on an {@see ObjectType}. The `type` drives validation and UI
 * rendering; `config` holds type-specific settings (select options, the target
 * object for relationships, currency code, AI prompt/output, etc.).
 *
 * @property string $type
 * @property array|null $config
 */
class Attribute extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'object_type_id', 'slug', 'name', 'type',
        'config', 'is_required', 'is_unique', 'is_title', 'position',
    ];

    protected $casts = [
        'config' => 'array',
        'is_required' => 'boolean',
        'is_unique' => 'boolean',
        'is_title' => 'boolean',
        'position' => 'integer',
    ];

    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTISELECT = 'multiselect';
    public const TYPE_RELATIONSHIP = 'relationship';
    public const TYPE_EMAIL = 'email';
    public const TYPE_URL = 'url';
    // Computed by AI (Phase 3). Values are written by ComputeAiAttributeJob and
    // are not directly user-writable.
    public const TYPE_AI = 'ai';

    public const TYPES = [
        self::TYPE_TEXT, self::TYPE_NUMBER, self::TYPE_CURRENCY, self::TYPE_DATE,
        self::TYPE_DATETIME, self::TYPE_CHECKBOX, self::TYPE_SELECT, self::TYPE_MULTISELECT,
        self::TYPE_RELATIONSHIP, self::TYPE_EMAIL, self::TYPE_URL, self::TYPE_AI,
    ];

    public function objectType(): BelongsTo
    {
        return $this->belongsTo(ObjectType::class);
    }

    public function isRelationship(): bool
    {
        return $this->type === self::TYPE_RELATIONSHIP;
    }

    public function isComputed(): bool
    {
        return $this->type === self::TYPE_AI;
    }

    /** Select/multiselect option list from config. */
    public function options(): array
    {
        return $this->config['options'] ?? [];
    }

    /** Target object slug for relationship attributes. */
    public function targetObjectSlug(): ?string
    {
        return $this->config['target_object'] ?? null;
    }

    /** Whether a relationship attribute holds multiple records. */
    public function isMultiRelationship(): bool
    {
        return $this->isRelationship() && (bool) ($this->config['many'] ?? false);
    }
}
