<?php

//capture.php

use Payum\Core\Reply\HttpResponse;
use Payum\Core\Reply\ReplyInterface;
use Payum\Core\Request\Capture;

include 'config.php';

$token = $payum->getHttpRequestVerifier()->verify($_REQUEST);
$gateway = $payum->getGateway($token->getGatewayName());

try {
    $gateway->execute(new Capture($token));

    if (false == isset($_REQUEST['noinvalidate'])) {
        $payum->getHttpRequestVerifier()->invalidate($token);
    }

    header('Location: '.$token->getAfterUrl());
} catch (HttpResponse $reply) {
    foreach ($reply->getHeaders() as $name => $value) {
        header("$name: $value");
    }

    http_response_code($reply->getStatusCode());
    echo $reply->getContent();

    exit;
} catch (ReplyInterface $reply) {
    throw new \LogicException('Unsupported reply', null, $reply);
}
