<?php

namespace App\Traits;

use Beansoft\LaraBase\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use ReflectionMethod;
use ReflectionNamedType;

trait ModelRequestLoader
{
    /**
     * Apply includes and appends to the model instance based on the request.
     *
     * @return Role|ModelRequestLoader
     */
    public function loadFromRequest(?Request $request = null): self
    {
        if (! $this instanceof Model) {
            return $this;
        }

        $request ??= request();

        // Handle includes (relationships)
        if ($request->filled('include')) {
            $includes = array_filter(
                array_map('trim', explode(',', $request->input('include'))),
                fn (string $relation) => $this->hasRelationship($relation)
            );

            if (! empty($includes)) {
                $loads = [];
                foreach ($includes as $include) {
                    if (blank($include)) {
                        continue;
                    }
                    $loads[] = $include;
                }
                if (! blank($loads)) {
                    $this->load($loads);
                }
            }
        }

        // Handle appends (attributes)
        if ($request->filled('append')) {
            $appends = array_filter(
                array_map('trim', explode(',', $request->input('append'))),
                fn (string $attribute) => $this->hasAttribute($attribute)
            );

            if (! empty($appends)) {
                $this->append($appends);
            }
        }

        return $this;
    }

    /**
     * Determine if the model has the given relationship.
     */
    private function hasRelationship(string $relationship): bool
    {
        $segments = explode('.', $relationship);
        $model = $this;

        foreach ($segments as $segment) {
            if (! method_exists($model, $segment)) {
                return false;
            }

            $reflection = new ReflectionMethod($model, $segment);
            $returnType = $reflection->getReturnType();

            if (! $returnType instanceof ReflectionNamedType ||
                ! is_subclass_of($returnType->getName(), Relation::class)) {
                return false;
            }

            /** @var Relation $relationInstance */
            $relationInstance = $model->{$segment}();
            $model = $relationInstance->getModel(); // Move deeper into nested relation
        }

        return true;
    }
}
