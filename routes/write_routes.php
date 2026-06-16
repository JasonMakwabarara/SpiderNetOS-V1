<?php
$content = '<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function getTenantId() {
    return "default";
}

Route::get("/agents", function () {
    return response()->json(DB::table("agents")->get());
});

Route::post("/agents", function (Request $request) {
    $id = (string) Str::uuid();
    DB::table("agents")->insert([
        "id" => $id,
        "tenant_id" => getTenantId(),
        "name" => $request->name,
        "description" => $request->description,
        "icon" => $request->icon ?? "🤖",
        "status" => $request->status ?? "inactive",
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    return response()->json(DB::table("agents")->where("id", $id)->first(), 201);
});

Route::put("/agents/{id}", function (Request $request, $id) {
    DB::table("agents")->where("id", $id)->update([
        "name" => $request->name,
        "description" => $request->description,
        "icon" => $request->icon,
        "status" => $request->status,
        "updated_at" => now(),
    ]);
    return response()->json(DB::table("agents")->where("id", $id)->first());
});

Route::delete("/agents/{id}", function ($id) {
    DB::table("agents")->where("id", $id)->delete();
    return response()->json(null, 204);
});

Route::post("/agents/{id}/run", function ($id, Request $request) {
    $agent = DB::table("agents")->where("id", $id)->first();
    if (!$agent) {
        return response()->json(["error" => "Agent not found"], 404);
    }
    
    $message = $request->input("message", "Hello");
    $apiKey = env("OPENAI_API_KEY");
    
    if (!$apiKey) {
        return response()->json(["result" => ["response" => "OpenAI API key not configured. Agent says: \'{$message}\'"]]);
    }
    
    try {
        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $apiKey,
            "Content-Type" => "application/json",
        ])->timeout(30)->post("https://api.openai.com/v1/chat/completions", [
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "system", "content" => "You are {$agent->name}. Be helpful and concise."],
                ["role" => "user", "content" => $message]
            ],
            "max_tokens" => 200,
        ]);
        
        if ($response->successful()) {
            return response()->json(["result" => ["response" => $response->json("choices.0.message.content")]]);
        }
        return response()->json(["result" => ["response" => "OpenAI error: " . $response->status()]]);
    } catch (Exception $e) {
        return response()->json(["result" => ["response" => "Error: " . $e->getMessage()]]);
    }
});

Route::get("/flows", function () {
    return response()->json(DB::table("flows")->get());
});

Route::post("/flows", function (Request $request) {
    $id = (string) Str::uuid();
    DB::table("flows")->insert([
        "id" => $id,
        "tenant_id" => getTenantId(),
        "name" => $request->name,
        "description" => $request->description,
        "trigger" => $request->trigger ?? "manual",
        "icon" => $request->icon ?? "⚡",
        "executions" => 0,
        "created_at" => now(),
        "updated_at" => now(),
    ]);
    return response()->json(DB::table("flows")->where("id", $id)->first(), 201);
});

Route::put("/flows/{id}", function (Request $request, $id) {
    DB::table("flows")->where("id", $id)->update([
        "name" => $request->name,
        "description" => $request->description,
        "trigger" => $request->trigger,
        "icon" => $request->icon,
        "updated_at" => now(),
    ]);
    return response()->json(DB::table("flows")->where("id", $id)->first());
});

Route::delete("/flows/{id}", function ($id) {
    DB::table("flows")->where("id", $id)->delete();
    return response()->json(null, 204);
});

Route::post("/flows/{id}/execute", function ($id) {
    $flow = DB::table("flows")->where("id", $id)->first();
    if (!$flow) return response()->json(["error" => "Flow not found"], 404);
    DB::table("flows")->where("id", $id)->increment("executions");
    return response()->json(["message" => "Flow executed"]);
});

Route::post("/atlas/chat", function (Request $request) {
    $text = $request->message;
    $lower = strtolower($text);
    
    if (str_contains($lower, "create agent")) {
        $name = trim(preg_replace("/create agent/i", "", $text));
        $id = (string) Str::uuid();
        DB::table("agents")->insert([
            "id" => $id,
            "tenant_id" => getTenantId(),
            "name" => $name,
            "status" => "inactive",
            "created_at" => now(),
            "updated_at" => now(),
        ]);
        return response()->json(["response" => "✅ Agent \'$name\' created!"]);
    }
    
    if (str_contains($lower, "create flow")) {
        $name = trim(preg_replace("/create flow/i", "", $text));
        $id = (string) Str::uuid();
        DB::table("flows")->insert([
            "id" => $id,
            "tenant_id" => getTenantId(),
            "name" => $name,
            "executions" => 0,
            "created_at" => now(),
            "updated_at" => now(),
        ]);
        return response()->json(["response" => "✅ Flow \'$name\' created!"]);
    }
    
    return response()->json(["response" => "Try: \"create agent [name]\" or \"create flow [name]\""]);
});

Route::get("/health", function () {
    return response()->json(["status" => "healthy"]);
});

Route::post("/login", function (Request $request) {
    if ($request->email === "admin@spidernetos.com" && $request->password === "Zukaarimoto01!") {
        return response()->json(["token" => "mock-token", "user" => ["name" => "Admin"]]);
    }
    return response()->json(["error" => "Invalid credentials"], 401);
});';

file_put_contents('routes/api.php', $content);
echo "Routes file written successfully!\n";