<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Agent extends Model
{
    protected $fillable = ['tenant_id', 'name', 'slug', 'type', 'role', 'capabilities', 'description', 'status', 'config', 'pack_id'];
    protected $casts = ['capabilities' => 'array', 'config' => 'array'];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = request()->header('X-Tenant-ID')) {
                $builder->where('tenant_id', $tenantId);
            }
        });
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function featurePack() { return $this->belongsTo(FeaturePack::class, 'pack_id'); }
}
