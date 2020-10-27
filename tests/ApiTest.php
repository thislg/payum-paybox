<?php

namespace Marem\PayumPaybox\Tests;

use Http\Message\MessageFactory\GuzzleMessageFactory;
use Marem\PayumPaybox\Api;
use Payum\Core\HttpClientInterface;
use Payum\Core\Request\GetHttpRequest;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    /**
     * @covers \Marem\PayumPaybox\Api::checkPayboxSignature
     *
     * @throws ReflectionException
     */
    public function testCheckPayboxSignatureShouldReturnTrue()
    {
        $api = $this->getApiMock();
        $httpRequest = new GetHttpRequest();
        $httpRequest->uri = 'https://foobar.com';
        $httpRequest->query = [
            'amount' => '22000',
            'reference' => 'CLI_5193_CDEW_5f918e4a5f1e2_394',
            'authorization_number' => 'XXXXXX',
            'error_code' => '00000',
            'card_type' => 'Maestro',
            'signature' => 'OOB5pT29r2GqsDOGyRktSB3AXdFSDOzIUihXNchZOjxOPgAbCpK0CVdD+NKtfEmsSjInY35bZ2A0nb5qCLKprZWRuGIbjgUrhHymm9tL/jfieE0x/4i7+QqI1ZY/TDQT+wsQ5eEJA+2AHOzZPcWHKekjuTC+nhhQBQqHuolV9vo=',
        ];
        self::assertTrue($this->invokeMethod($api, 'checkPayboxSignature', [$httpRequest]), 'verify should return true');
    }

    /**
     * @covers \Marem\PayumPaybox\Api::checkPayboxSignature
     *
     * @throws ReflectionException
     */
    public function testCheckPayboxSignatureShouldReturnFalseBecauseInvalidSignture()
    {
        $api = $this->getApiMock();
        $httpRequest = new GetHttpRequest();
        $httpRequest->uri = 'https://foobar.com';
        $httpRequest->query = [
            'amount' => '22000',
            'reference' => 'CLI_5193_CDEW_5f918e4a5f1e2_394',
            'authorization_number' => 'XXXXXX',
            'error_code' => '00000',
            'card_type' => 'Maestro',
            // I deleted the first character
            'signature' => 'OB5pT29r2GqsDOGyRktSB3AXdFSDOzIUihXNchZOjxOPgAbCpK0CVdD+NKtfEmsSjInY35bZ2A0nb5qCLKprZWRuGIbjgUrhHymm9tL/jfieE0x/4i7+QqI1ZY/TDQT+wsQ5eEJA+2AHOzZPcWHKekjuTC+nhhQBQqHuolV9vo=',
        ];
        self::assertFalse($this->invokeMethod($api, 'checkPayboxSignature', [$httpRequest]), 'verify should return false');
    }

    /**
     * @covers \Marem\PayumPaybox\Api::checkPayboxSignature
     *
     * @throws ReflectionException
     */
    public function testCheckPayboxSignatureShouldReturnFalseBecauseSignatureNotSet()
    {
        $api = $this->getApiMock();
        $httpRequest = new GetHttpRequest();
        $httpRequest->uri = 'https://foobar.com';
        $httpRequest->query = [
            'amount' => '22000',
            'reference' => 'CLI_5193_CDEW_5f918e4a5f1e2_394',
            'authorization_number' => 'XXXXXX',
            'error_code' => '00000',
            'card_type' => 'Maestro',
        ];
        self::assertFalse($this->invokeMethod($api, 'checkPayboxSignature', [$httpRequest]), 'verify should return false cuz signature not set');
    }

    /**
     * @covers \Marem\PayumPaybox\Api::checkPayboxSignature
     *
     * @throws ReflectionException
     */
    public function testCheckPayboxSignatureShouldReturnFalseBecauseAQueryParameterHasChanged()
    {
        $api = $this->getApiMock();
        $httpRequest = new GetHttpRequest();
        $httpRequest->uri = 'https://foobar.com';
        $httpRequest->query = [
            // The query param amount changed (2200 => 100)
            'amount' => '100',
            'reference' => 'CLI_5193_CDEW_5f918e4a5f1e2_394',
            'authorization_number' => 'XXXXXX',
            'error_code' => '00000',
            'card_type' => 'Maestro',
        ];
        self::assertFalse($this->invokeMethod($api, 'checkPayboxSignature', [$httpRequest]), 'verify should return false cuz a query param changed');
    }

    /**
     * @covers \Marem\PayumPaybox\Api::checkOriginIpAddress
     * @dataProvider checkOriginIpAddressProvider
     */
    public function testCheckOriginIpAddress($request, $expected)
    {
        $api = $this->getApiMock();
        self::assertSame($expected, $this->invokeMethod($api, 'checkOriginIpAddress', [$request]));
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array  $parameters array of parameters to pass into method
     *
     * @return mixed method return
     *
     * @throws ReflectionException
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(\get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    protected function createHttpClientMock()
    {
        return $this->createMock(HttpClientInterface::class);
    }

    /**
     * @return GuzzleMessageFactory
     */
    protected function createHttpMessageFactory()
    {
        return new GuzzleMessageFactory();
    }

    public function checkOriginIpAddressProvider()
    {
        $okPreprodIncoming = new GetHttpRequest();
        $okPreprodIncoming->clientIp = '195.101.99.73';
        $ko = new GetHttpRequest();
        $ko->clientIp = '192.101.99.73';
        $okProductionIncoming = new GetHttpRequest();
        $okProductionIncoming->clientIp = '194.2.160.91';

        return [
            [$okPreprodIncoming, true],
            [$ko, false],
            [$okProductionIncoming, true],
        ]
            ;
    }

    private function getApiMock(): Api
    {
        $api = new Api(
            $options = [
                'site' => '',
                'rang' => '',
                'identifiant' => '',
                'hash' => '',
                'retour' => '',
                'type_paiement' => '',
                'type_carte' => '',
                'hmac' => '',
            ],
            $this->createHttpClientMock(),
            $this->createHttpMessageFactory()
        );

        return $api;
    }
}
