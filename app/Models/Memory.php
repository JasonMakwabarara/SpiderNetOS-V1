<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Memory extends Model
{
    protected $table = 'memories';
    protected $fillable = ['tenant_id', 'content', 'embedding', 'metadata'];
    protected $casts = ['metadata' => 'array', 'embedding' => 'array'];

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
