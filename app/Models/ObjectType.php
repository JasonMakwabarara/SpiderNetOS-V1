<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-defined (or system) object type, e.g. People, Companies, Deals, or any
 * custom business entity. This is the Attio-style flexible data model: each
 * object owns a set of typed {@see Attribute}s and contains {@see Record}s.
 */
class ObjectType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'slug', 'name', 'icon', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    /**
     * Bind route params (objects/{objectType}) by slug, scoped to the tenant via
     * the BelongsToTenant global scope.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class)->orderBy('position');
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    public function titleAttribute(): ?Attribute
    {
        // Note: `attributes` collides with Eloquent's internal $attributes
        // property, so never read it via $this->attributes inside the model.
        // Use the loaded relation if present, otherwise query it.
        $defs = $this->relationLoaded('attributes')
            ? $this->getRelation('attributes')
            : $this->attributes()->get();

        return $defs->firstWhere('is_title', true) ?? $defs->first();
    }
}
