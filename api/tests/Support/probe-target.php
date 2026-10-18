<?php

declare(strict_types=1);

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$twoMebibytes = 2 * 1024 * 1024;

if (preg_match('#^/status/(\d{3})$#', $path, $matches) === 1) {
    http_response_code((int) $matches[1]);
    exit('status '.$matches[1]);
}

if (preg_match('#^/record/([a-z0-9]+)$#', $path, $matches) === 1) {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $record = ['method' => $_SERVER['REQUEST_METHOD'] ?? '', 'headers' => $headers, 'body' => (string) file_get_contents('php://input')];
    file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR."uply-probe-{$matches[1]}.json", json_encode($record));
    exit('recorded');
}

if (preg_match('#^/hop/(30[1-8])/([a-z0-9]+)$#', $path, $matches) === 1) {
    header("Location: /record/{$matches[2]}", true, (int) $matches[1]);
    exit;
}

if (preg_match('#^/hop-away/(30[1-8])/([a-z0-9]+)$#', $path, $matches) === 1) {
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    header("Location: http://localhost:{$port}/record/{$matches[2]}", true, (int) $matches[1]);
    exit;
}

switch ($path) {
    case '/slow':
        sleep(2);
        exit('late');
    case '/redirect-to-ok':
        header('Location: /status/200', true, 302);
        exit;
    case '/redirect-loop':
        header('Location: /redirect-loop', true, 302);
        exit;
    case '/redirect-private':
        header('Location: http://192.168.1.10/admin', true, 302);
        exit;
    case '/redirect-metadata':
        header('Location: http://169.254.169.254/latest/meta-data/', true, 302);
        exit;
    case '/big':
        exit(str_repeat('u', $twoMebibytes));
    case '/big-declared':
        header('Content-Length: '.$twoMebibytes);
        exit(str_repeat('u', $twoMebibytes));
}

http_response_code(404);
exit('missing');
