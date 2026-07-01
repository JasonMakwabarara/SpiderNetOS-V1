<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Flow;
use App\Models\ObjectType;
use App\Services\AtlasAssistant;
use App\Services\EventLogger;
use Illuminate\Http\Request;

/**
 * Atlas — the conversational "Ask SpiderNet" assistant. Natural-language requests
 * are handled by an LLM tool-calling loop ({@see AtlasAssistant}); a few slash
 * commands are kept as instant, deterministic shortcuts.
 */
class AtlasController extends Controller
{
    public function __construct(protected AtlasAssistant $assistant)
    {
    }

    public function chat(Request $request)
    {
        $text = trim($request->input('message', ''));

        if ($text === '') {
            return response()->json(['response' => 'Ask me anything, or type /help.']);
        }

        if (str_starts_with($text, '/')) {
            return response()->json(['response' => $this->slashCommand(strtolower($text))]);
        }

        EventLogger::log('atlas.chat', '', ['len' => strlen($text)]);
        $response = $this->assistant->ask($text);

        return response()->json(['response' => $response]);
    }

    protected function slashCommand(string $cmd): string
    {
        return match ($cmd) {
            '/agents' => $this->bullets('Agents', Agent::all()->map(fn ($a) => "{$a->name} ({$a->status})")),
            '/flows' => $this->bullets('Flows', Flow::all()->map(fn ($f) => "{$f->name} (runs: {$f->executions})")),
            '/objects' => $this->bullets('Objects', ObjectType::all()->map(fn ($o) => "{$o->name} [{$o->slug}]")),
            '/status' => "**System Status**\n- Agents: ".Agent::count()
                ."\n- Flows: ".Flow::count()
                ."\n- Objects: ".ObjectType::count(),
            '/help' => "**Atlas commands**\n\n".
                "Just talk to me naturally — I can search and create records, run agents, build flows, and search your knowledge base.\n\n".
                "Shortcuts:\n- `/agents` list agents\n- `/flows` list flows\n- `/objects` list data objects\n- `/status` system status\n- `/help` this message",
            default => "Unknown command. Type `/help`.",
        };
    }

    protected function bullets(string $title, $items): string
    {
        $list = $items->map(fn ($i) => "• {$i}")->implode("\n");

        return "**Your {$title}:**\n".($list ?: 'None yet.');
    }
}
