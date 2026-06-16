<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agent;
use App\Models\Flow;
use Illuminate\Support\Str;

class AtlasController extends Controller
{
    public function chat(Request $request)
    {
        $text = $request->input('message', '');
        $lower = strtolower($text);
        
        if (str_contains($lower, 'create agent')) {
            $name = trim(preg_replace('/create agent/i', '', $text));
            if (empty($name)) {
                return response()->json(['response' => 'Please specify an agent name']);
            }
            $agent = Agent::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Created via Atlas",
                'status' => 'inactive',
                'tenant_id' => 1
            ]);
            return response()->json(['response' => "✅ Agent '$name' created!"]);
        }
        
        if (str_contains($lower, 'create flow')) {
            $name = trim(preg_replace('/create flow/i', '', $text));
            if (empty($name)) {
                return response()->json(['response' => 'Please specify a flow name']);
            }
            $flow = Flow::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Created via Atlas",
                'trigger' => 'manual',
                'tenant_id' => 1
            ]);
            return response()->json(['response' => "✅ Flow '$name' created!"]);
        }
        
        if ($lower === '/agents' || str_contains($lower, 'list agents')) {
            $agents = Agent::all();
            $list = "**📋 Your Agents:**\n";
            foreach ($agents as $a) {
                $list .= "• {$a['name']} ({$a['status']})\n";
            }
            return response()->json(['response' => $list ?: 'No agents yet.']);
        }
        
        if ($lower === '/flows' || str_contains($lower, 'list flows')) {
            $flows = Flow::all();
            $list = "**📋 Your Flows:**\n";
            foreach ($flows as $f) {
                $list .= "• {$f['name']} (runs: {$f['executions']})\n";
            }
            return response()->json(['response' => $list ?: 'No flows yet.']);
        }
        
        if ($lower === '/status') {
            $agentsCount = Agent::count();
            $flowsCount = Flow::count();
            return response()->json(['response' => "**System Status:**\n- Agents: $agentsCount\n- Flows: $flowsCount"]);
        }
        
        if ($lower === '/help') {
            return response()->json(['response' => "**Available Commands:**\n\n• `create agent [name]` - Create a new AI agent\n• `create flow [name]` - Create a new workflow\n• `/agents` - List all agents\n• `/flows` - List all flows\n• `/status` - System status\n• `/help` - Show this help"]);
        }
        
        return response()->json(['response' => "I'm here to help you manage your AI Operating System. Try `/help` for commands."]);
    }
}