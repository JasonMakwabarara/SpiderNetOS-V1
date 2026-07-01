<?php

namespace App\Http\Controllers;

use App\Models\ObjectType;
use App\Models\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ViewController extends Controller
{
    public function index(Request $request)
    {
        $query = View::query()->with('objectType:id,slug,name');

        if ($slug = $request->query('object')) {
            $object = ObjectType::where('slug', $slug)->first();
            $query->where('object_type_id', $object?->id ?? 0);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'object' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in([View::TYPE_TABLE, View::TYPE_KANBAN])],
            'config' => ['nullable', 'array'],
        ]);

        $object = ObjectType::where('slug', $validated['object'])->firstOrFail();

        $view = View::create([
            'object_type_id' => $object->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::random(5),
            'type' => $validated['type'],
            'config' => $validated['config'] ?? [],
        ]);

        return response()->json($view->load('objectType:id,slug,name'), 201);
    }

    public function update(Request $request, View $view)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', Rule::in([View::TYPE_TABLE, View::TYPE_KANBAN])],
            'config' => ['nullable', 'array'],
        ]);

        $view->update($validated);

        return response()->json($view->fresh());
    }

    public function destroy(View $view)
    {
        $view->delete();

        return response()->json(null, 204);
    }
}
