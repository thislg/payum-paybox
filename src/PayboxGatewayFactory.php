<?php

namespace Marem\PayumPaybox;

use Marem\PayumPaybox\Action\CancelAction;
use Marem\PayumPaybox\Action\CaptureAction;
use Marem\PayumPaybox\Action\ChoosePaymentTypeAction;
use Marem\PayumPaybox\Action\ConvertPaymentAction;
use Marem\PayumPaybox\Action\NotifyAction;
use Marem\PayumPaybox\Action\RefundAction;
use Marem\PayumPaybox\Action\StatusAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class PayboxGatewayFactory extends GatewayFactory implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param ArrayObject<string> $config
     */
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'paybox',
            'payum.factory_title' => 'Paybox',
            'payum.action.capture' => new CaptureAction(),
            'payum.action.refund' => new RefundAction(),
            'payum.action.cancel' => new CancelAction(),
            'payum.action.notify' => new NotifyAction(),
            'payum.action.status' => new StatusAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
            'payum.template.choose_card_type' => '@PayumPaybox/Action/choose_payment_type.html.twig',
            'payum.action.choose_payment_type' => function (ArrayObject $config) {
                return new ChoosePaymentTypeAction($config['payum.template.choose_card_type']);
            },
        ]);

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = [
                'site' => '',
                'rang' => '',
                'identifiant' => '',
                'hmac' => '',
                'hash' => 'SHA512',
                'retour' => 'amount:M;reference:R;authorization_number:A;error_code:E;card_type:C;signature:K',
                'sandbox' => true,
                'type_paiement' => '',
                'type_carte' => '',
            ];
            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = ['site', 'rang', 'identifiant', 'hmac'];

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                return new Api((array) $config, $config['payum.http_client'], $config['httplug.message_factory']);
            };
        }

        $config['payum.paths'] = array_replace([
            'PayumPaybox' => __DIR__.'/Resources/views',
        ], $config['payum.paths'] ?: []);
    }
}
