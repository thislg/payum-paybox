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
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\RenderTemplate;

class ChoosePaymentTypeAction extends GatewayAwareAction implements ApiAwareInterface
{
    use ApiAwareTrait;

    /**
     * @var string
     */
    protected $templateName;

    /**
     * @param string $templateName
     */
    public function __construct($templateName)
    {
        $this->apiClass = Api::class;
        $this->templateName = $templateName;
    }

    /**
     * {@inheritdoc}
     *
     * @param ChoosePaymentType $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = ArrayObject::ensureArrayObject($request->getModel());

        $getHttpRequest = new GetHttpRequest();
        $this->gateway->execute($getHttpRequest);

        /* if form has been submitted, set the payment type and card type to complete the payment details*/
        if ('POST' == $getHttpRequest->method && isset($getHttpRequest->request['paymentType'])) {
            $details[PayBoxRequestParams::PBX_TYPEPAIEMENT] = $getHttpRequest->request['paymentType'];
            $details[PayBoxRequestParams::PBX_TYPECARTE] = $getHttpRequest->request['cardType'];

            return;
        }

        $template = new RenderTemplate($this->templateName, [
            'model' => $details,
            'actionUrl' => $request->getToken() ? $request->getToken()->getTargetUrl() : null,
        ]);

        $this->gateway->execute($template);

        throw new HttpResponse($template->getResult());
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof ChoosePaymentType &&
            $request->getModel() instanceof \ArrayAccess
            ;
    }
}
