<?php

namespace Marem\PayumPaybox\Action;

use Marem\PayumPaybox\ApiLoggerAwareAction;
use Marem\PayumPaybox\PayBoxRequestParams;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\Convert;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryAwareTrait;

class ConvertPaymentAction extends ApiLoggerAwareAction implements ActionInterface, GenericTokenFactoryAwareInterface
{
    use GenericTokenFactoryAwareTrait;

    /**
     * {@inheritdoc}
     *
     * @param Convert $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getSource();

        $details = ArrayObject::ensureArrayObject($payment->getDetails());
        $details[PayBoxRequestParams::PBX_TOTAL] = $payment->getTotalAmount();
        //TODO : dynamise currency code.
        $details[PayBoxRequestParams::PBX_DEVISE] = '978';
        $details[PayBoxRequestParams::PBX_CMD] = $payment->getNumber();
        $details[PayBoxRequestParams::PBX_PORTEUR] = $payment->getClientEmail();
        $token = $request->getToken();
        $details[PayBoxRequestParams::PBX_EFFECTUE] = $token->getTargetUrl();
        $details[PayBoxRequestParams::PBX_ANNULE] = $token->getTargetUrl();
        $details[PayBoxRequestParams::PBX_REFUSE] = $token->getTargetUrl();
        $dateTime = date('c');
        $details[PayBoxRequestParams::PBX_TIME] = $dateTime;

        if (false === isset($details[PayBoxRequestParams::PBX_REPONDRE_A])) {
            $notifyToken = $this->tokenFactory->createNotifyToken($token->getGatewayName(), $payment);
            $targetUrl = $notifyToken->getTargetUrl();
            $details[PayBoxRequestParams::PBX_REPONDRE_A] = $targetUrl;
            $this->logger->notice('[Paybox] PBX_REPONDRE_A', [
                'targetUrl' => $targetUrl,
            ]);
        }

        $request->setResult((array) $details);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Convert &&
            $request->getSource() instanceof PaymentInterface &&
            'array' === $request->getTo()
        ;
    }
}
