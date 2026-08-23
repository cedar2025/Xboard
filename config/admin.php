<?php

return [
    // Comma-separated IPs/CIDRs allowed to reach admin routes.
    // Empty (default) disables the restriction.
    // Example: ADMIN_IP_ALLOWLIST=1.2.3.4,10.0.0.0/8
    'ip_allowlist' => env('ADMIN_IP_ALLOWLIST', ''),
];
