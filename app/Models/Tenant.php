<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'domain', 'settings'];
    protected $casts = ['settings' => 'array'];

    public function agents() { return $this->hasMany(Agent::class); }
    public function flows() { return $this->hasMany(Flow::class); }
    public function featurePacks() { return $this->hasMany(FeaturePack::class); }
}
