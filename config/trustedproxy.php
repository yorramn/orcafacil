<?php

$raw = trim((string) env('TRUSTED_PROXIES', ''));
$proxies = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $proxy): bool => $proxy !== '' && $proxy !== '*'));

return [
    // Configure explicit proxy IPs/CIDRs in TRUSTED_PROXIES. Empty or '*' trusts none.
    'proxies' => $proxies === [] ? null : $proxies,
];
