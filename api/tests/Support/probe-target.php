<?php

declare(strict_types=1);

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$twoMebibytes = 2 * 1024 * 1024;

if (preg_match('#^/status/(\d{3})$#', $path, $matches) === 1) {
    http_response_code((int) $matches[1]);
    exit('status '.$matches[1]);
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
