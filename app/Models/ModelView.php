<?php

namespace App\Models;

use App\Traits\ModelRequestLoader;
use App\Traits\Scopeable;
use Database\Factories\ModelViewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelView extends Model
{
    /** @use HasFactory<ModelViewFactory> */
    use HasFactory;
    use HasUuids;
    use ModelRequestLoader;
    use Scopeable;

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
