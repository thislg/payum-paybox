<?php

namespace Marem\PayumPaybox;

use Http\Message\MessageFactory;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\HttpClientInterface;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use RuntimeException;

class Api implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    /**
     * Primary server.
     */
    const MAIN_SERVER = 'tpeweb.paybox.com';

    /**
     * Backup server.
     */
    const BACKUP_SERVER = 'tpeweb1.paybox.com';

    /**
     * Sandbox server.
     */
    const SANDBOX_SERVER = 'preprod-tpeweb.paybox.com';

    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var array|array{site:string, rang: string, identifiant:string, hash: string, retour: string, type_paiement: string, type_carte: string, hmac: string}
     */
    protected $options = [];

    const PAYBOX_IP_ADDRESSES = [
        // incoming ip addresses
        // pre-production
        '195.101.99.73',
        // production
        '194.2.160.66',
        '194.2.160.80',
        '194.2.160.82',
        '194.2.160.91',
        '195.25.7.146',
        '195.25.67.0',
        '195.25.67.2',
        '195.25.67.11',
        // outgoing ip addresses
        // pre-production
        '195.101.99.76',
        //production
        '194.2.122.158',
        '194.2.122.190',
        '195.25.7.166',
        '195.25.67.22',
    ];

    public function __construct(array $options, HttpClientInterface $client, MessageFactory $messageFactory)
    {
        $this->options = $options;
        $this->client = $client;
        $this->messageFactory = $messageFactory;
        $this->setLogger(new NullLogger());
    }

    public function doPayment(array $fields): void
    {
        $fields[PayBoxRequestParams::PBX_SITE] = $this->options['site'];
        $fields[PayBoxRequestParams::PBX_RANG] = $this->options['rang'];
        $fields[PayBoxRequestParams::PBX_IDENTIFIANT] = $this->options['identifiant'];
        $fields[PayBoxRequestParams::PBX_HASH] = $this->options['hash'];
        $fields[PayBoxRequestParams::PBX_RETOUR] = $this->options['retour'];
        $fields[PayBoxRequestParams::PBX_TYPEPAIEMENT] = $this->options['type_paiement'];
        $fields[PayBoxRequestParams::PBX_TYPECARTE] = $this->options['type_carte'];
        $fields[PayBoxRequestParams::PBX_HMAC] = strtoupper($this->computeHmac($this->options['hmac'], $fields));

        $authorizeTokenUrl = $this->getAuthorizeTokenUrl();
        throw new HttpPostRedirect($authorizeTokenUrl, $fields);
    }

    protected function doRequest(string $method, array $fields): ResponseInterface
    {
        $headers = [];

        $request = $this->messageFactory->createRequest($method, $this->getApiEndpoint(), $headers, http_build_query($fields));

        $response = $this->client->send($request);

        if (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300)) {
            throw HttpException::factory($request, $response);
        }

        return $response;
    }

    /**
     * Get api end point.
     *
     * @return string server url
     *
     * @throws RuntimeException if no server available
     */
    protected function getApiEndpoint()
    {
        $servers = [];
        if ($this->options['sandbox']) {
            $servers[] = self::SANDBOX_SERVER;
        } else {
            $servers = [self::MAIN_SERVER, self::BACKUP_SERVER];
        }

        foreach ($servers as $server) {
            $doc = new \DOMDocument();
            $doc->loadHTMLFile('https://'.$server.'/load.html');

            $element = $doc->getElementById('server_status');
            if ($element && 'OK' === $element->textContent) {
                return $server;
            }
        }

        throw new RuntimeException('No server available.');
    }

    /**
     * @return string
     */
    public function getAuthorizeTokenUrl()
    {
        return sprintf(
            'https://%s/cgi/MYchoix_pagepaiement.cgi',
            $this->getApiEndpoint()
        );
    }

    /**
     * @param string $hmac   hmac key
     * @param array  $fields fields
     *
     * @return string
     */
    protected function computeHmac($hmac, $fields)
    {
        // Si la clé est en ASCII, On la transforme en binaire
        $binKey = pack('H*', $hmac);
        $msg = http_build_query($fields);

        return strtoupper(hash_hmac($fields[PayBoxRequestParams::PBX_HASH], $msg, $binKey));
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @See documentation ManuelIntegrationVerifone_PayboxSystem_V8 5.3.4 Values checking
     *
     * @param GetHttpRequest $httpRequest
     *
     * @return bool
     */
    public function verify($httpRequest)
    {
        $context = [
            'httpRequestUri' => $httpRequest->uri,
            'httpRequestQuery' => $httpRequest->query,
        ];
        if (false === $this->doesTheResponseReallyComeFromPaybox($httpRequest)) {
            $message = 'Notification invalid: the response doesn\'t come from Paybox.';
            $this->logger->error($message, $context);
            throw new HttpResponse($message);
        }

        return true;
    }

    /**
     * @param GetHttpRequest $httpRequest
     *
     * @return bool
     */
    private function doesTheResponseReallyComeFromPaybox($httpRequest)
    {
        // checks paybox signature
        $this->logger->notice('checkPayboxSignature', [
            'return' => $this->checkPayboxSignature($httpRequest),
        ]);
        if (false === $this->checkPayboxSignature($httpRequest)) {
            return false;
        }
        // checks origin ip address
        $this->logger->notice('checkOriginIpAddress', [
            'return' => $this->checkOriginIpAddress($httpRequest),
        ]);
        if (false === $this->checkOriginIpAddress($httpRequest)) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function checkOriginIpAddress(GetHttpRequest $httpRequest)
    {
        return \in_array($httpRequest->clientIp, self::PAYBOX_IP_ADDRESSES, true);
    }

    /**
     * @return bool
     */
    private function checkPayboxSignature(GetHttpRequest $httpRequest)
    {
        if (false === isset($httpRequest->query['signature'])) {
            $this->logger->critical('checkPayboxSignature: query parameter \'signature\' not set', [
                'query' => $httpRequest->query,
            ]);

            return false;
        }

        // init signature
        $signature = $httpRequest->query['signature'];
        $signatureLength = \strlen($signature);
        if ($signatureLength > 172) {
            $signatureInitialized = base64_decode(urldecode($signature));
        } elseif (172 == $signatureLength) {
            $signatureInitialized = base64_decode($signature);
        } elseif (128 == $signatureLength) {
            $signatureInitialized = $signature;
        } else {
            $this->logger->error('Fail to base_decode signature', [
                    'signature' => $signature,
                ]);

            return false;
        }

        $file = fopen(__DIR__.'/Resources/pubkey.pem', 'rb');
        if (false === $file) {
            $this->logger->error('Can`t open Paybox API pubkey file');

            return false;
        }
        $cert = fread($file, 1024);
        fclose($file);
        if (false === $cert) {
            $this->logger->error('Can`t read Paybox API pubkey');

            return false;
        }
        $publicKeyId = openssl_pkey_get_public($cert);
        if (false === $publicKeyId) {
            $this->logger->error('Fail to extract pubkey from certificate');

            return false;
        }

        $this->logger->debug('[Paybox] arrayData', [
            'httpRequestQuery' => $httpRequest->query,
        ]);

        /** @var array $arrayDataExcludingSignature Signed data */
        $arrayDataExcludingSignature = array_filter($httpRequest->query, function ($value, $key) {
            return 'signature' !== $key;
        }, \ARRAY_FILTER_USE_BOTH);
        $this->logger->debug('[Paybox] arrayDataExcludingSignature', [
            'arrayDataExcludingSignature' => $arrayDataExcludingSignature,
        ]);
        $data = http_build_query($arrayDataExcludingSignature);

        $this->logger->debug('[Paybox] Verify signature', [
            'data' => urlencode($data),
            'signatureInitialized' => urlencode($signatureInitialized),
        ]);
        $result = openssl_verify(
            $data,
            $signatureInitialized,
            $publicKeyId,
            'sha1WithRSAEncryption'
        );
        openssl_free_key($publicKeyId);
        if (1 === $result) {
            // singature valid
            $this->logger->notice('checkPayboxSignature: valid signature', [
                'signature' => $httpRequest->query['signature'],
            ]);

            return true;
        }
        if (0 === $result) {
            // signature invalid
            $this->logger->critical('checkPayboxSignature: invalid signature', [
                'signature' => $httpRequest->query['signature'],
            ]);

            return false;
        }
        if (-1 === $result) {
            // error while verifying signature
            $this->logger->critical('checkPayboxSignature: error while verifying signature', [
                'signature' => $httpRequest->query['signature'],
            ]);

            return false;
        }
    }
}
