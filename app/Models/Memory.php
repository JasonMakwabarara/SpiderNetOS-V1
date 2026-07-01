<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Memory extends Model
{
    use BelongsToTenant;

    protected $table = 'memories';

    protected $fillable = ['tenant_id', 'source_type', 'source_id', 'content', 'embedding', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
