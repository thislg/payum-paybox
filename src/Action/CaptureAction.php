<?php

namespace Marem\PayumPaybox\Action;

use Marem\PayumPaybox\Api;
use Marem\PayumPaybox\PayBoxRequestParams;
use Marem\PayumPaybox\Request\Api\ChoosePaymentType;
use Payum\Core\Action\GatewayAwareAction;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;

class CaptureAction extends GatewayAwareAction implements ApiAwareInterface
{
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * {@inheritdoc}
     *
     * @param Capture $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        /* if no payment type provided in config, execute a choose payment type action
         so that user can choose the payment type*/
        if (null == $details[PayBoxRequestParams::PBX_TYPEPAIEMENT]
            && null == $this->api->getOptions()['type_paiement']) {
            $choosePaymentTypeRequest = new ChoosePaymentType($details);
            $this->gateway->execute($choosePaymentTypeRequest);
        }

        $httpRequest = new GetHttpRequest();
        $this->gateway->execute($httpRequest);

        if (isset($httpRequest->query['error_code'])) {
            $details->replace($httpRequest->query);
        } else {
            $this->api->doPayment((array) $details);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
