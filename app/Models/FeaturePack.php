<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FeaturePack extends Model
{
    protected $table = 'feature_packs';
    protected $fillable = ['tenant_id', 'name', 'version', 'publisher', 'signature', 'manifest', 'status', 'installed_at'];
    protected $casts = ['manifest' => 'array', 'installed_at' => 'datetime'];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = request()->header('X-Tenant-ID')) {
                $builder->where('tenant_id', $tenantId);
            }
        });
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function agents() { return $this->hasMany(Agent::class, 'pack_id'); }
}