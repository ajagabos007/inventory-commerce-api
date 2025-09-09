<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ModelRequestLoader
{
    /**
     * Apply includes and appends to the model instance based on the request.
     *
     * @param  Illuminate\Http\Request|null  $request
     * @return $this
     */
    public function applyRequestIncludesAndAppends(?Request $request = null): self
    {
        if (! $this instanceof Model) {
            return $this;
        }

        if (is_null($request)) {
            $request = request();
        }

        // Handle includes (relationships)
        if ($request->has('include')) {
            $relations = [];
            $relationCounts = [];

            foreach (explode(',', $request->input('include')) as $relation) {
                if (\str_contains($relation, 'Count')) {
                    $rel_count = str_replace('Count', '', $relation);
                    $relationCounts[] = $rel_count;
                } else {
                    $relations[] = $relation;
                }
            }

            try {
                if (! empty($relations)) {
                    $this->load($relations);
                }
                if (! empty($relationCounts)) {
                    $this->loadCount($relationCounts);
                }
            } catch (\Throwable $th) {
                // Optionally handle exception
            }
        }

        // Handle appends (attributes)
        if ($request->has('append')) {
            $appends = array_filter(
                explode(',', $request->input('append')),
                fn ($attribute) => $this->hasAttribute($attribute)
            );

            if (! empty($appends)) {
                $this->append($appends);
            }
        }

        return $this;
    }
}
