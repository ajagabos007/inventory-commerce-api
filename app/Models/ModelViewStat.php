<?php

namespace App\Models;

use App\Traits\ModelRequestLoader;
use App\Traits\Scopeable;
use Database\Factories\ModelViewStatFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelViewStat extends Model
{
    /** @use HasFactory<ModelViewStatFactory> */
    use HasFactory;
    use HasUuids;
    use Scopeable;
    use ModelRequestLoader;

    protected $fillable = [
        'viewable_id',
        'viewable_type',
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'viewed_at'
    ];

    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
}
