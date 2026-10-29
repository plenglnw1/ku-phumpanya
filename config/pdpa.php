<?php

declare(strict_types=1);

return [
    // Bump when the privacy notice changes; users who accepted an older version
    // are asked again (frontend compares /api/user consent_version with this).
    'consent_version' => env('PDPA_CONSENT_VERSION', '2026-10'),
];
