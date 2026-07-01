<?php

namespace App\Http\Controllers;

use App\Services\AtlasTools;
use Illuminate\Http\Request;

/**
 * Minimal Model Context Protocol (MCP) server over HTTP/JSON-RPC 2.0. Exposes the
 * workspace's {@see AtlasTools} surface to external MCP clients (IDEs, AI agents).
 *
 * The request is authenticated like any other API call (Sanctum token), so tool
 * execution is scoped to the caller's tenant. Supported methods:
 *   initialize · tools/list · tools/call · ping
 */
class McpController extends Controller
{
    private const PROTOCOL_VERSION = '2024-11-05';

    public function __construct(protected AtlasTools $tools)
    {
    }

    public function handle(Request $request)
    {
        $payload = $request->json()->all();

        // JSON-RPC notifications (no id) get an empty 202 ack.
        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? null;
        $params = $payload['params'] ?? [];

        if ($method === null) {
            return $this->error($id, -32600, 'Invalid Request: missing method.');
        }

        return match ($method) {
            'initialize' => $this->result($id, [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => ['tools' => (object) []],
                'serverInfo' => ['name' => 'SpiderNetOS', 'version' => '1.0.0'],
            ]),
            'notifications/initialized' => response()->json(null, 202),
            'ping' => $this->result($id, (object) []),
            'tools/list' => $this->result($id, ['tools' => $this->toolList()]),
            'tools/call' => $this->callTool($id, $params),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    /** Convert OpenAI-format definitions into MCP tool descriptors. */
    protected function toolList(): array
    {
        return collect($this->tools->definitions())->map(function ($def) {
            $fn = $def['function'];

            return [
                'name' => $fn['name'],
                'description' => $fn['description'],
                'inputSchema' => $fn['parameters'],
            ];
        })->values()->all();
    }

    protected function callTool($id, array $params)
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if ($name === '') {
            return $this->error($id, -32602, 'Invalid params: tool name is required.');
        }

        // AtlasTools::execute returns a JSON string with an `ok` flag.
        $raw = $this->tools->execute($name, is_array($arguments) ? $arguments : []);
        $decoded = json_decode($raw, true);
        $isError = is_array($decoded) && ($decoded['ok'] ?? true) === false;

        return $this->result($id, [
            'content' => [['type' => 'text', 'text' => $raw]],
            'isError' => $isError,
        ]);
    }

    protected function result($id, $result)
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    protected function error($id, int $code, string $message)
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }
}
