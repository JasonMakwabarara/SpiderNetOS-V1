<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\ObjectType;
use App\Services\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manages the typed attributes (fields) of an object type.
 */
class AttributeController extends Controller
{
    public function index(ObjectType $objectType)
    {
        return response()->json($objectType->attributes()->get());
    }

    public function store(Request $request, ObjectType $objectType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|alpha_dash',
            'type' => ['required', Rule::in(Attribute::TYPES)],
            'config' => 'nullable|array',
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_title' => 'boolean',
            'position' => 'nullable|integer',
        ]);

        $this->validateConfig($validated['type'], $validated['config'] ?? []);

        $attribute = $objectType->attributes()->create([
            'tenant_id' => $objectType->tenant_id,
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name'], '_'),
            'type' => $validated['type'],
            'config' => $validated['config'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
            'is_unique' => $validated['is_unique'] ?? false,
            'is_title' => $validated['is_title'] ?? false,
            'position' => $validated['position'] ?? ($objectType->attributes()->max('position') + 1),
        ]);

        EventLogger::log('attribute.created', (string) $attribute->id, [
            'object' => $objectType->slug,
            'slug' => $attribute->slug,
            'type' => $attribute->type,
        ]);

        return response()->json($attribute, 201);
    }

    public function update(Request $request, Attribute $attribute)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'config' => 'nullable|array',
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_title' => 'boolean',
            'position' => 'nullable|integer',
        ]);

        if (array_key_exists('config', $validated)) {
            $this->validateConfig($attribute->type, $validated['config'] ?? []);
        }

        $attribute->update($validated);
        EventLogger::log('attribute.updated', (string) $attribute->id, ['slug' => $attribute->slug]);

        return response()->json($attribute);
    }

    public function destroy(Attribute $attribute)
    {
        EventLogger::log('attribute.deleted', (string) $attribute->id, ['slug' => $attribute->slug]);
        $attribute->delete();

        return response()->json(null, 204);
    }

    /**
     * Guard against incomplete config for types that need it.
     */
    protected function validateConfig(string $type, array $config): void
    {
        if (in_array($type, [Attribute::TYPE_SELECT, Attribute::TYPE_MULTISELECT], true)
            && empty($config['options'])) {
            abort(422, "A '{$type}' attribute requires a non-empty config.options array.");
        }

        if ($type === Attribute::TYPE_RELATIONSHIP && empty($config['target_object'])) {
            abort(422, "A 'relationship' attribute requires config.target_object (an object slug).");
        }

        if ($type === Attribute::TYPE_AI && empty($config['prompt'])) {
            abort(422, "An 'ai' attribute requires config.prompt.");
        }
    }
}
