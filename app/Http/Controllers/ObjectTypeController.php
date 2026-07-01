<?php

namespace App\Http\Controllers;

use App\Models\ObjectType;
use App\Services\EventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD for user-defined object types (the Attio-style flexible data model).
 * Tenant isolation is enforced by the ObjectType global scope.
 */
class ObjectTypeController extends Controller
{
    public function index()
    {
        return response()->json(
            ObjectType::withCount(['attributes', 'records'])->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|alpha_dash',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        $objectType = ObjectType::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => $validated['icon'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        EventLogger::log('object_type.created', (string) $objectType->id, ['slug' => $objectType->slug]);

        return response()->json($objectType, 201);
    }

    public function show(ObjectType $objectType)
    {
        $objectType->load('attributes');

        return response()->json($objectType);
    }

    public function update(Request $request, ObjectType $objectType)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $objectType->update($validated);
        EventLogger::log('object_type.updated', (string) $objectType->id, ['slug' => $objectType->slug]);

        return response()->json($objectType);
    }

    public function destroy(ObjectType $objectType)
    {
        if ($objectType->is_system) {
            return response()->json(['message' => 'System objects cannot be deleted.'], 422);
        }

        EventLogger::log('object_type.deleted', (string) $objectType->id, ['slug' => $objectType->slug]);
        $objectType->delete();

        return response()->json(null, 204);
    }
}
