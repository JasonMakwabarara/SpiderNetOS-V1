<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug', 'type', 'role', 'capabilities', 'description', 'status', 'config', 'pack_id'];

    protected $casts = ['capabilities' => 'array', 'config' => 'array'];

    public function featurePack()
    {
        return $this->belongsTo(FeaturePack::class, 'pack_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }
}
