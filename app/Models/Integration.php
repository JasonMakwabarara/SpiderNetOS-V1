<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-scoped external connector (Slack, email, WhatsApp, CRM).
 * Credentials are encrypted at rest and never exposed in API responses.
 */
class Integration extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'inbound_token', 'type', 'status',
        'credentials', 'config', 'last_error', 'last_synced_at',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'config' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = ['credentials'];

    protected $appends = ['inbound_url', 'oauth_connected'];

    protected static function booted(): void
    {
        static::creating(function (Integration $integration) {
            if ($integration->type === self::TYPE_EMAIL && empty($integration->inbound_token)) {
                $integration->inbound_token = \Illuminate\Support\Str::random(40);
            }
        });
    }

    public function getInboundUrlAttribute(): ?string
    {
        if ($this->type !== self::TYPE_EMAIL || ! $this->inbound_token) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/api/hooks/email/'.$this->inbound_token;
    }

    public function getOauthConnectedAttribute(): bool
    {
        if ($this->type !== self::TYPE_HUBSPOT) {
            return false;
        }

        $credentials = $this->credentials ?? [];

        return ! empty($credentials['refresh_token'])
            || ! empty($credentials['access_token'])
            || ! empty($credentials['api_key']);
    }

    public const TYPE_SLACK = 'slack';
    public const TYPE_EMAIL = 'email';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_HUBSPOT = 'hubspot';
    public const TYPE_SALESFORCE = 'salesforce';

    public const STATUS_CONNECTED = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_ERROR = 'error';

    /** @return array<int, string> */
    public static function types(): array
    {
        return [
            self::TYPE_SLACK,
            self::TYPE_EMAIL,
            self::TYPE_WHATSAPP,
            self::TYPE_HUBSPOT,
            self::TYPE_SALESFORCE,
        ];
    }
}
