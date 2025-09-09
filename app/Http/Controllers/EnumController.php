<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use ReflectionEnum;
use Symfony\Component\Finder\Finder;

class EnumController extends Controller
{
    private const NAMESPACE = 'App\\Enums\\';

    private const CACHE_TTL = 3600; // 1 hour

    public function __invoke(Request $request): JsonResponse
    {
        $enumName = $request->get('enum');

        if ($enumName) {
            return $this->getEnumValues($enumName);
        }

        return $this->getAvailableEnums();
    }

    private function getAvailableEnums(): JsonResponse
    {
        $enums = Cache::remember('available_enums', self::CACHE_TTL, function () {
            return $this->discoverEnums();
        });

        return response()->json([
            'enums' => array_map(fn ($class) => class_basename($class), $enums),
        ]);
    }

    private function getEnumValues(string $enumName): JsonResponse
    {
        $className = self::NAMESPACE.$enumName;

        if (! enum_exists($className)) {
            return response()->json(['error' => 'Enum not found'], 404);
        }

        $values = Cache::remember("enum_values_{$enumName}", self::CACHE_TTL, function () use ($className) {
            $reflection = new ReflectionEnum($className);
            $cases = $reflection->getCases();

            return array_map(function ($case) {
                $data = ['name' => $case->name];

                // Add value for backed enums
                if ($case instanceof \ReflectionEnumBackedCase) {
                    $data['value'] = $case->getBackingValue();
                }

                // Add label if method exists
                $enum = $case->getValue();
                if (method_exists($enum, 'label')) {
                    $data['label'] = $enum->label();
                }

                return $data;
            }, $cases);
        });

        return response()->json([
            'enum' => $enumName,
            'values' => $values,
        ]);
    }

    private function discoverEnums(): array
    {
        $path = app_path('Enums');

        if (! is_dir($path)) {
            return [];
        }

        $enums = [];

        foreach ((new Finder)->files()->in($path)->name('*.php') as $file) {
            $className = self::NAMESPACE.pathinfo($file->getFilename(), PATHINFO_FILENAME);

            if (enum_exists($className)) {
                $enums[] = $className;
            }
        }

        return $enums;
    }
}
