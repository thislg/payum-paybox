<?php

namespace Marem\PayumPaybox;

use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\GatewayAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class ApiLoggerAwareAction implements ApiAwareInterface, LoggerAwareInterface
{
    use ApiAwareTrait;
    use GatewayAwareTrait;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->api->setLogger($logger);
        $this->logger = $logger;
    }
}
