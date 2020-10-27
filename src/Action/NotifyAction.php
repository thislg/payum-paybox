<?php

namespace Marem\PayumPaybox\Action;

use Marem\PayumPaybox\Api;
use Marem\PayumPaybox\PayboxRetour;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class NotifyAction implements ApiAwareInterface, ActionInterface, GatewayAwareInterface, LoggerAwareInterface
{
    use ApiAwareTrait;
    use GatewayAwareTrait;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct()
    {
        $this->apiClass = Api::class;
        $this->logger = new NullLogger();
    }

    /**
     * @See documentation ManuelIntegrationVerifone_PayboxSystem_V8 5.3 Instant Payment Notification
     *
     * {@inheritdoc}
     *
     * @param Notify $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());
        $context = [
            'httpRequestUri' => $httpRequest->uri,
            'httpRequestQuery' => $httpRequest->query,
        ];

        // check eletronic signature is valid
        if (false === $this->api->verify($httpRequest)) {
            throw new HttpResponse('The notification is invalid. Code 1', 400);
        }
        // check authorization number is existing
        if (false === isset($httpRequest->query['authorization_number'])) {
            $message = 'Notification invalid: authorization number invalid.';
            $this->logger->error($message, $context);
            throw new HttpResponse($message, 400);
        }
        // check error code equals to 00000
        $errorCode = $httpRequest->query['error_code'] ?? null;
        if ('00000' !== $errorCode) {
            $message = 'Notification invalid: transaction rejected, '.PayboxRetour::get($errorCode)->getReadable();
            $this->logger->error($message, $context);
            throw new HttpResponse($message, 400);
        }
        // check the amount equals the original amount
        $queryAmount = $httpRequest->query['amount'] ?? null;
        if ($details['amount'] !== $queryAmount) {
            $message = 'Notification invalid: transaction invalid, amount differs from original';
            $this->logger->error($message, $context);
            throw new HttpResponse($message, 400);
        }
        $this->logger->info('Notification valid', $context);

        $details->replace($httpRequest->query);

        throw new HttpResponse('OK', 200);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Notify &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->api->setLogger($logger);
        $this->logger = $logger;
    }
}
