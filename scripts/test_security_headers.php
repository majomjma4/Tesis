<?php

declare(strict_types=1);

/** Performs a read-only HTTP smoke test for the response security headers. */
if (PHP_SAPI !== 'cli') exit(1);

$url = trim((string) ($argv[1] ?? 'http://127.0.0.1/TESIS/index.php?page=login'));
$parts = parse_url($url);
if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
    fwrite(STDERR, "Usage: php scripts/test_security_headers.php [http(s)://host/path]\n");
    exit(1);
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'ignore_errors' => true,
        'max_redirects' => 0,
        'timeout' => 15,
        'header' => "Accept: text/html\r\nConnection: close\r\n",
    ],
]);
$body = @file_get_contents($url, false, $context);
$rawHeaders = $http_response_header ?? [];
$headers = [];
foreach ($rawHeaders as $headerLine) {
    if (!str_contains($headerLine, ':')) continue;
    [$name, $value] = explode(':', $headerLine, 2);
    $headers[strtolower(trim($name))] = trim($value);
}

$required = [
    'x-content-type-options' => 'nosniff',
    'referrer-policy' => 'strict-origin-when-cross-origin',
    'x-frame-options' => 'SAMEORIGIN',
    'permissions-policy' => 'camera=(), geolocation=(), microphone=(), payment=(), usb=()',
];
$missing = [];
$mismatched = [];
foreach ($required as $name => $expected) {
    if (!array_key_exists($name, $headers)) {
        $missing[] = $name;
    } elseif ($headers[$name] !== $expected) {
        $mismatched[$name] = ['expected' => $expected, 'actual' => $headers[$name]];
    }
}
if (!array_key_exists('content-security-policy', $headers)) $missing[] = 'content-security-policy';

$scheme = strtolower((string) $parts['scheme']);
$host = strtolower((string) $parts['host']);
$localHttp = $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
$unexpectedLocalHsts = $localHttp && array_key_exists('strict-transport-security', $headers);
$status = $rawHeaders[0] ?? 'HTTP response unavailable';
$result = [
    'url' => $url,
    'status' => $status,
    'request_succeeded' => $body !== false || $rawHeaders !== [],
    'required_headers_present' => $missing === [],
    'missing_headers' => array_values(array_unique($missing)),
    'mismatched_headers' => $mismatched,
    'local_http_hsts_absent' => !$unexpectedLocalHsts,
    'security_headers' => $headers,
    'passed' => $missing === [] && $mismatched === [] && !$unexpectedLocalHsts,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($result['passed'] ? 0 : 2);
