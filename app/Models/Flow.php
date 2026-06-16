<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Flow extends Model
{
    protected $fillable = ['tenant_id', 'name', 'slug', 'trigger', 'config', 'executions'];
    protected $casts = ['config' => 'array'];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = request()->header('X-Tenant-ID')) {
                $builder->where('tenant_id', $tenantId);
            }
        });
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }
}
