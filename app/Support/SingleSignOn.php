<?php

namespace App\Support;

/**
 * Whether signing in through the institution's Authelia is offered, and what
 * the sign-in screen calls it. Read from configuration on every call, so it
 * carries no state between requests.
 */
final class SingleSignOn
{
    public const DRIVER = 'authelia';

    public function enabled(): bool
    {
        return filled(config('services.authelia.base_url'))
            && filled(config('services.authelia.client_id'));
    }

    public function label(): string
    {
        return (string) (config('services.authelia.label') ?: 'Masuk dengan SSO');
    }
}
