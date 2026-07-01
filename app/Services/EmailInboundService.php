<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Integration;
use App\Models\ObjectType;
use App\Models\Record;
use Illuminate\Http\Request;

/**
 * Parses inbound email webhook payloads and attaches them to the CRM timeline.
 */
class EmailInboundService
{
    /**
     * Normalise provider-specific webhook fields into a common shape.
     *
     * @return array{from:string,to:string,subject:string,body:string,message_id:?string}
     */
    public function parse(Request $request): array
    {
        $data = $request->all();

        $from = $this->firstString($data, ['from', 'sender', 'envelope.from', 'From']);
        $to = $this->firstString($data, ['to', 'recipient', 'envelope.to', 'To']);
        $subject = $this->firstString($data, ['subject', 'Subject', 'headers.Subject']);
        $body = $this->firstString($data, ['body', 'text', 'plain', 'stripped-text', 'html', 'stripped-html']);
        $messageId = $this->firstString($data, ['message_id', 'Message-Id', 'message-id', 'headers.Message-Id']);

        if ($from === '' && isset($data['from_full'])) {
            $from = is_array($data['from_full'])
                ? ($data['from_full']['email'] ?? '')
                : (string) $data['from_full'];
        }

        return [
            'from' => $this->extractEmail($from),
            'to' => $this->extractEmail($to),
            'subject' => $subject,
            'body' => $body,
            'message_id' => $messageId !== '' ? $messageId : null,
        ];
    }

    public function handle(Integration $integration, array $email): array
    {
        if ($email['from'] === '' && $email['subject'] === '' && $email['body'] === '') {
            return ['ok' => false, 'message' => 'Empty inbound email payload.', 'activity_id' => null];
        }

        if ($email['message_id']) {
            $exists = Activity::query()
                ->where('type', 'email')
                ->where('meta->message_id', $email['message_id'])
                ->exists();

            if ($exists) {
                return ['ok' => true, 'message' => 'Duplicate message ignored.', 'activity_id' => null];
            }
        }

        $record = $this->matchRecord($integration, $email['from']);
        $title = $email['subject'] !== '' ? $email['subject'] : 'Inbound email';

        $activity = ActivityLogger::log($record, 'email', $title, [
            'body' => $email['body'] !== '' ? $email['body'] : null,
            'icon' => '📥',
            'is_system' => true,
            'meta' => array_filter([
                'direction' => 'inbound',
                'from' => $email['from'] ?: null,
                'to' => $email['to'] ?: null,
                'message_id' => $email['message_id'],
                'integration_id' => $integration->id,
            ]),
            'occurred_at' => now(),
        ]);

        FlowDispatcher::onInboundEmail($integration, $email, $record, $activity);

        return ['ok' => true, 'message' => 'Inbound email captured.', 'activity_id' => $activity->id];
    }

    protected function matchRecord(Integration $integration, string $fromEmail): ?Record
    {
        if ($fromEmail === '') {
            return null;
        }

        $objectSlug = $integration->config['email_match_object'] ?? 'people';
        $emailField = $integration->config['email_match_field'] ?? 'email';

        $object = ObjectType::where('slug', $objectSlug)->first();
        if (! $object) {
            return null;
        }

        return Record::query()
            ->where('object_type_id', $object->id)
            ->where("data->{$emailField}", $fromEmail)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    protected function firstString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    protected function extractEmail(string $value): string
    {
        if (preg_match('/<([^>]+)>/', $value, $m)) {
            return strtolower(trim($m[1]));
        }

        return strtolower(trim($value));
    }
}
