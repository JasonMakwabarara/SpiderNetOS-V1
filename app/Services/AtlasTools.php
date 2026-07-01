<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Flow;
use App\Models\ObjectType;
use App\Models\Record;
use Illuminate\Support\Str;

/**
 * The tool surface Atlas exposes to the LLM. Each tool maps to a tenant-scoped
 * action over the records, agents, flows, and semantic memory of the current
 * tenant. Tools are defined in OpenAI function-calling format and dispatched by
 * name from {@see AtlasAssistant}.
 */
class AtlasTools
{
    public function __construct(
        protected RecordWriter $records,
        protected MemoryService $memory,
        protected ConversationMemory $conversation,
        protected InferenceService $inference,
    ) {
    }

    /**
     * OpenAI-format tool/function definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            $this->fn('list_objects', 'List the data objects (record types) available, e.g. people, companies, deals.', [
                'type' => 'object', 'properties' => (object) [], 'required' => [],
            ]),
            $this->fn('search_records', 'Search records of an object by free text and/or exact field filters.', [
                'type' => 'object',
                'properties' => [
                    'object' => ['type' => 'string', 'description' => 'Object slug, e.g. companies'],
                    'query' => ['type' => 'string', 'description' => 'Free-text search'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results (default 10)'],
                ],
                'required' => ['object'],
            ]),
            $this->fn('create_record', 'Create a record of an object. Provide field values keyed by attribute slug.', [
                'type' => 'object',
                'properties' => [
                    'object' => ['type' => 'string', 'description' => 'Object slug, e.g. people'],
                    'data' => ['type' => 'object', 'description' => 'Attribute slug => value map'],
                ],
                'required' => ['object', 'data'],
            ]),
            $this->fn('list_agents', 'List the AI agents in this workspace.', [
                'type' => 'object', 'properties' => (object) [], 'required' => [],
            ]),
            $this->fn('create_agent', 'Create a new AI agent.', [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'role' => ['type' => 'string', 'description' => 'Sales|Support|Data Analyst|Developer|HR|Custom'],
                    'description' => ['type' => 'string'],
                ],
                'required' => ['name'],
            ]),
            $this->fn('run_agent', 'Send a message to an agent and return its reply.', [
                'type' => 'object',
                'properties' => [
                    'agent' => ['type' => 'string', 'description' => 'Agent slug or id'],
                    'message' => ['type' => 'string'],
                ],
                'required' => ['agent', 'message'],
            ]),
            $this->fn('list_flows', 'List the automation workflows in this workspace.', [
                'type' => 'object', 'properties' => (object) [], 'required' => [],
            ]),
            $this->fn('create_flow', 'Create a new (manual) workflow.', [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'trigger' => ['type' => 'string', 'description' => 'manual|webhook|schedule'],
                ],
                'required' => ['name'],
            ]),
            $this->fn('semantic_search', 'Search the knowledge base and past interactions by meaning.', [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
                'required' => ['query'],
            ]),
            $this->fn('remember', 'Persist a durable fact about the user for future personalisation.', [
                'type' => 'object',
                'properties' => [
                    'fact' => ['type' => 'string'],
                ],
                'required' => ['fact'],
            ]),
        ];
    }

    /**
     * Execute a tool by name. Always returns a string (JSON) for the model.
     */
    public function execute(string $name, array $args): string
    {
        try {
            return match ($name) {
                'list_objects' => $this->listObjects(),
                'search_records' => $this->searchRecords($args),
                'create_record' => $this->createRecord($args),
                'list_agents' => $this->listAgents(),
                'create_agent' => $this->createAgent($args),
                'run_agent' => $this->runAgent($args),
                'list_flows' => $this->listFlows(),
                'create_flow' => $this->createFlow($args),
                'semantic_search' => $this->semanticSearch($args),
                'remember' => $this->rememberFact($args),
                default => $this->err("Unknown tool '{$name}'."),
            };
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->err('Validation failed: '.implode(' ', $e->validator->errors()->all()));
        } catch (\Throwable $e) {
            return $this->err($e->getMessage());
        }
    }

    // ---- Tool implementations --------------------------------------------

    protected function listObjects(): string
    {
        $objects = ObjectType::withCount('records')->get()
            ->map(fn (ObjectType $o) => [
                'slug' => $o->slug,
                'name' => $o->name,
                'records' => $o->records_count,
                'fields' => $o->attributes()->get()->map(fn ($a) => ['slug' => $a->slug, 'type' => $a->type])->all(),
            ]);

        return $this->ok($objects);
    }

    protected function searchRecords(array $args): string
    {
        $object = $this->requireObject($args['object'] ?? '');
        $limit = min((int) ($args['limit'] ?? 10), 50);

        $query = Record::query()->where('object_type_id', $object->id);

        if (! empty($args['filter']) && is_array($args['filter'])) {
            foreach ($args['filter'] as $slug => $value) {
                $query->where("data->{$slug}", $value);
            }
        }

        if ($q = ($args['query'] ?? null)) {
            $textSlugs = $object->attributes()
                ->whereIn('type', ['text', 'email', 'url'])
                ->pluck('slug');
            $query->where(function ($sub) use ($textSlugs, $q) {
                foreach ($textSlugs as $slug) {
                    $sub->orWhere("data->{$slug}", 'like', '%'.$q.'%');
                }
            });
        }

        $rows = $query->latest()->limit($limit)->get()
            ->map(fn (Record $r) => ['id' => $r->id, 'title' => $r->title(), 'data' => $r->data]);

        return $this->ok(['count' => $rows->count(), 'records' => $rows]);
    }

    protected function createRecord(array $args): string
    {
        $object = $this->requireObject($args['object'] ?? '');
        $data = $args['data'] ?? [];
        if (! is_array($data) || empty($data)) {
            return $this->err('Provide a non-empty data object keyed by attribute slug.');
        }

        $record = $this->records->create($object, $data);

        return $this->ok(['created' => true, 'id' => $record->id, 'title' => $record->title()]);
    }

    protected function listAgents(): string
    {
        return $this->ok(Agent::all(['id', 'name', 'slug', 'role', 'status']));
    }

    protected function createAgent(array $args): string
    {
        $name = trim($args['name'] ?? '');
        if ($name === '') {
            return $this->err('An agent name is required.');
        }

        $agent = Agent::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'role' => $args['role'] ?? null,
            'description' => $args['description'] ?? 'Created via Atlas',
            'status' => 'active',
        ]);

        return $this->ok(['created' => true, 'id' => $agent->id, 'slug' => $agent->slug]);
    }

    protected function runAgent(array $args): string
    {
        $agent = $this->resolveAgent($args['agent'] ?? '');
        if (! $agent) {
            return $this->err('Agent not found.');
        }
        $message = trim($args['message'] ?? '');
        if ($message === '') {
            return $this->err('A message is required.');
        }

        $reply = $this->inference->chat($message, $agent);

        return $this->ok(['agent' => $agent->name, 'reply' => $reply]);
    }

    protected function listFlows(): string
    {
        return $this->ok(Flow::all(['id', 'name', 'slug', 'trigger', 'executions']));
    }

    protected function createFlow(array $args): string
    {
        $name = trim($args['name'] ?? '');
        if ($name === '') {
            return $this->err('A flow name is required.');
        }

        $flow = Flow::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'trigger' => $args['trigger'] ?? 'manual',
        ]);

        return $this->ok(['created' => true, 'id' => $flow->id, 'slug' => $flow->slug]);
    }

    protected function semanticSearch(array $args): string
    {
        $query = trim($args['query'] ?? '');
        if ($query === '') {
            return $this->err('A query is required.');
        }

        $results = $this->memory->search($query, 5)
            ->map(fn ($r) => ['content' => $r['content'], 'score' => $r['score']]);

        return $this->ok(['results' => $results]);
    }

    protected function rememberFact(array $args): string
    {
        $fact = trim($args['fact'] ?? '');
        if ($fact === '') {
            return $this->err('Nothing to remember.');
        }
        $this->conversation->remember($fact);

        return $this->ok(['remembered' => $fact]);
    }

    // ---- Helpers ----------------------------------------------------------

    protected function requireObject(string $slug): ObjectType
    {
        $object = ObjectType::where('slug', $slug)->first();
        if (! $object) {
            throw new \RuntimeException("Object '{$slug}' does not exist. Use list_objects to see valid slugs.");
        }

        return $object;
    }

    protected function resolveAgent(string $ref): ?Agent
    {
        if ($ref === '') {
            return null;
        }

        return is_numeric($ref)
            ? Agent::find((int) $ref)
            : Agent::where('slug', $ref)->orWhere('name', $ref)->first();
    }

    protected function fn(string $name, string $description, array $parameters): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ],
        ];
    }

    protected function ok(mixed $data): string
    {
        return json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES);
    }

    protected function err(string $message): string
    {
        return json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    }
}
