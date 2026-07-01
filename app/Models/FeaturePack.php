<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FeaturePack extends Model
{
    use BelongsToTenant;

    protected $table = 'feature_packs';

    protected $fillable = ['tenant_id', 'name', 'version', 'publisher', 'signature', 'manifest', 'status', 'installed_at'];

    protected $casts = ['manifest' => 'array', 'installed_at' => 'datetime'];

    public function agents()
    {
        return $this->hasMany(Agent::class, 'pack_id');
    }
}
