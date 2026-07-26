<?php

// GET /api/v2/capabilities.php
// Fork detection probe: fork identity + implemented v2 feature list.
// Stock ITFlow 404s this path; consumers key stock-vs-fork off that.

$REQUIRE_METHOD = 'GET';
require_once __DIR__ . '/_lib/bootstrap.php';

api_ok([
    'fork'          => true,
    'fork_api'      => FORK_API_VERSION,
    'upstream_base' => FORK_UPSTREAM_BASE,
    'features'      => FORK_FEATURES,
    'v1_extensions' => FORK_V1_EXTENSIONS,
]);
