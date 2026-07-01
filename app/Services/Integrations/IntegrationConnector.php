<?php

namespace App\Services\Integrations;

use App\Models\Integration;

interface IntegrationConnector
{
    /** @return array{ok: bool, message: string} */
    public function test(Integration $integration): array;

    /** @return array{ok: bool, message: string, data?: mixed} */
    public function send(Integration $integration, string $action, array $payload): array;

    /** Import/sync external data into SpiderNetOS records. */
    public function sync(Integration $integration): array;
}
