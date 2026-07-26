<?php

// api/v2 typed parameter readers. Missing -> default; invalid -> 422.

function v2_p_int(array $src, string $key, ?int $default = null): ?int
{
    if (!isset($src[$key]) || $src[$key] === '') {
        return $default;
    }
    if (!is_numeric($src[$key]) || intval($src[$key]) != $src[$key]) {
        api_fail(422, 'VALIDATION_FAILED', "$key must be an integer.", $key);
    }
    return intval($src[$key]);
}

function v2_p_str(array $src, string $key, ?string $default = null): ?string
{
    if (!isset($src[$key])) {
        return $default;
    }
    if (!is_string($src[$key]) && !is_numeric($src[$key])) {
        api_fail(422, 'VALIDATION_FAILED', "$key must be a string.", $key);
    }
    $val = trim((string) $src[$key]);
    return $val === '' ? $default : $val;
}

function v2_p_enum(array $src, string $key, array $allowed, ?string $default = null): ?string
{
    $val = v2_p_str($src, $key, $default);
    if ($val === null) {
        return null;
    }
    foreach ($allowed as $option) {
        if (strcasecmp($val, $option) === 0) {
            return $option;
        }
    }
    api_fail(422, 'VALIDATION_FAILED', "$key must be one of: " . implode(', ', $allowed) . ".", $key);
}

function v2_p_date(array $src, string $key, ?string $default = null): ?string
{
    $val = v2_p_str($src, $key, $default);
    if ($val === null) {
        return null;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $val, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        api_fail(422, 'VALIDATION_FAILED', "$key must be a valid YYYY-MM-DD date.", $key);
    }
    return $val;
}

// Pagination: page >= 1, per_page 1..100. Returns page/per_page/limit/offset.
function v2_p_page(array $src): array
{
    $page = v2_p_int($src, 'page', 1);
    $per_page = v2_p_int($src, 'per_page', 25);
    if ($page < 1) {
        api_fail(422, 'VALIDATION_FAILED', 'page must be >= 1.', 'page');
    }
    if ($per_page < 1 || $per_page > 100) {
        api_fail(422, 'VALIDATION_FAILED', 'per_page must be between 1 and 100.', 'per_page');
    }
    return [
        'page'     => $page,
        'per_page' => $per_page,
        'limit'    => $per_page,
        'offset'   => ($page - 1) * $per_page,
    ];
}
