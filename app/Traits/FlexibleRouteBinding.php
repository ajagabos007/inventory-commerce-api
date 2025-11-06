<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait FlexibleRouteBinding
{
    /**
     * The columns this model can be resolved by in route binding.
     *
     * @var array<int, string>
     */
    protected static array $routeBindingKeys = ['id', 'slug'];

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        // If a specific field is requested, use it
        if ($field) {
            return $this->where($field, $value)->firstOrFail();
        }

        // Otherwise, try all configured keys
        $keys = static::$routeBindingKeys ?? ['id', 'slug'];

        return static::query()
            ->whereAny($keys, $value)
            ->firstOrFail();
    }
}
