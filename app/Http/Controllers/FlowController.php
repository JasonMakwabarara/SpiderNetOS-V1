<?php

namespace App\Http\Controllers;

use App\Models\Flow;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class FlowController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID', 1);
        $flows = Flow::where('tenant_id', $tenantId)->get();
        return response()->json($flows);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'trigger' => 'nullable|string',
            'config' => 'nullable|array'
        ]);
        
        $validated['slug'] = Str::slug($validated['name']);
        $validated['tenant_id'] = $request->header('X-Tenant-ID', 1);
        
        if (isset($validated['config'])) {
            $validated['config'] = json_encode($validated['config']);
        }
        
        $flow = Flow::create($validated);
        return response()->json($flow, 201);
    }

    public function update(Request $request, Flow $flow)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'trigger' => 'nullable|string',
            'config' => 'nullable|array'
        ]);
        
        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        
        if (isset($validated['config'])) {
            $validated['config'] = json_encode($validated['config']);
        }
        
        $flow->update($validated);
        return response()->json($flow);
    }

    public function destroy(Flow $flow)
    {
        $flow->delete();
        return response()->json(null, 204);
    }

    public function execute(Flow $flow)
    {
        // Increment execution counter
        $flow->increment('executions');
        
        // Get actions from config
        $config = json_decode($flow->config, true);
        $actions = $config['actions'] ?? [];
        
        $logFile = storage_path('../data/email_log.txt');
        
        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            
            if ($type === 'slack') {
                $webhook = $action['webhook'] ?? '';
                $message = $action['message'] ?? 'Flow executed';
                if ($webhook) {
                    try {
                        Http::post($webhook, ['text' => $message]);
                    } catch (\Exception $e) {
                        Log::error("Slack webhook failed: " . $e->getMessage());
                    }
                }
            } elseif ($type === 'webhook') {
                $url = $action['url'] ?? '';
                $data = $action['data'] ?? [];
                if ($url) {
                    try {
                        Http::post($url, $data);
                    } catch (\Exception $e) {
                        Log::error("Webhook failed: " . $e->getMessage());
                    }
                }
            } elseif ($type === 'email') {
                $to = $action['to'] ?? '';
                $subject = $action['subject'] ?? 'Flow Executed';
                $body = $action['body'] ?? 'The flow was executed successfully.';
                
                try {
                    // Send real email using Laravel Mail
                    Mail::raw($body, function ($message) use ($to, $subject) {
                        $message->to($to)
                                ->subject($subject)
                                ->from(env('MAIL_FROM_ADDRESS', 'noreply@spidernetos.com'), env('MAIL_FROM_NAME', 'SpiderNetOS'));
                    });
                    
                    $logEntry = date('Y-m-d H:i:s') . " - EMAIL SENT to: $to | SUBJECT: $subject\n";
                    file_put_contents($logFile, $logEntry, FILE_APPEND);
                    
                } catch (\Exception $e) {
                    $logEntry = date('Y-m-d H:i:s') . " - EMAIL FAILED to: $to | ERROR: " . $e->getMessage() . "\n";
                    file_put_contents($logFile, $logEntry, FILE_APPEND);
                }
            }
        }
        
        return response()->json([
            'message' => "Flow '{$flow->name}' executed successfully",
            'executions' => $flow->executions
        ]);
    }
}