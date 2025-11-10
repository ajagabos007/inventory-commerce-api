<?php

namespace App\Models;

use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
