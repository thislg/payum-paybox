<?php

namespace Marem\PayumPaybox\Tests\Action;

use Marem\PayumPaybox\Action\NotifyAction;
use Marem\PayumPaybox\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;
use Payum\Core\Tests\GenericActionTest;
use Psr\Log\LoggerAwareInterface;
use stdClass;

class NotifyActionTest extends GenericActionTest
{
    protected $actionClass = NotifyAction::class;

    protected $requestClass = Notify::class;

    public function testShouldImplementGatewayAwareInterface()
    {
        $reflectionClass = new \ReflectionClass(NotifyAction::class);
        self::assertTrue($reflectionClass->implementsInterface(GatewayAwareInterface::class));
    }

    public function testShouldImplementApiAwareInterface()
    {
        $reflectionClass = new \ReflectionClass(NotifyAction::class);
        self::assertTrue($reflectionClass->implementsInterface(ApiAwareInterface::class));
    }

    public function testShouldImplementActionInterface()
    {
        $reflectionClass = new \ReflectionClass(NotifyAction::class);
        self::assertTrue($reflectionClass->implementsInterface(ActionInterface::class));
    }

    public function testShouldImplementLoggerAwareInterface()
    {
        $reflectionClass = new \ReflectionClass(NotifyAction::class);
        self::assertTrue($reflectionClass->implementsInterface(LoggerAwareInterface::class));
    }

    /**
     * @test
     * @covers \Marem\PayumPaybox\Action\NotifyAction::setApi
     */
    public function throwIfUnsupportedApiGiven()
    {
        $this->expectException(UnsupportedApiException::class);
        $action = new NotifyAction();
        $action->setApi(new stdClass());
    }

    /**
     * @covers \Marem\PayumPaybox\Action\NotifyAction::execute
     */
    public function testExecuteShouldThrow200()
    {
        $gatewayMock = $this->createGatewayMock();
        $gatewayMock
            ->expects(self::once())
            ->method('execute')
            ->with(self::isInstanceOf(GetHttpRequest::class))
            ->willReturnCallback(function (GetHttpRequest $request) {
                $request->query = [
                    'amount' => 100,
                    'authorization_number' => 'XXXXX',
                    'error_code' => '00000',
                ];
            })
        ;

        $apiMock = $this->createApiMock();
        $apiMock
            ->expects(self::once())
            ->method('verify')
            ->willReturn(true)
        ;

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);
        $action->setApi($apiMock);

        try {
            $action->execute(new Notify([
                'amount' => 100,
            ]));
        } catch (HttpResponse $reply) {
            self::assertSame('OK', $reply->getContent());
            self::assertSame(200, $reply->getStatusCode());

            return;
        }
    }

    /**
     * @covers \Marem\PayumPaybox\Action\NotifyAction::execute
     */
    public function testExecuteShouldThrow400BecauseInvalidAuthorizationNumber()
    {
        $gatewayMock = $this->createGatewayMock();
        $gatewayMock
            ->expects(self::once())
            ->method('execute')
            ->with(self::isInstanceOf(GetHttpRequest::class))
            ->willReturnCallback(function (GetHttpRequest $request) {
                $request->query = ['amount' => 100];
            })
        ;

        $apiMock = $this->createApiMock();
        $apiMock
            ->expects(self::once())
            ->method('verify')
            ->willReturn(true)
        ;

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);
        $action->setApi($apiMock);

        try {
            $action->execute(new Notify([
                'amount' => 200,
            ]));
        } catch (HttpResponse $reply) {
            self::assertSame(400, $reply->getStatusCode());
            self::assertSame('Notification invalid: authorization number invalid.', $reply->getContent());

            return;
        }

        self::fail('The exception is expected');
    }

    /**
     * @covers \Marem\PayumPaybox\Action\NotifyAction::execute
     */
    public function testExecuteShouldThrow400BecauseInvalidErrorCode()
    {
        $gatewayMock = $this->createGatewayMock();
        $gatewayMock
            ->expects(self::once())
            ->method('execute')
            ->with(self::isInstanceOf(GetHttpRequest::class))
            ->willReturnCallback(function (GetHttpRequest $request) {
                $request->query = ['amount' => 100];
            })
        ;

        $apiMock = $this->createApiMock();
        $apiMock
            ->expects(self::once())
            ->method('verify')
            ->willReturn(true)
        ;

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);
        $action->setApi($apiMock);

        try {
            $action->execute(new Notify([
                'amount' => 200,
            ]));
        } catch (HttpResponse $reply) {
            self::assertSame(400, $reply->getStatusCode());
            self::assertSame('Notification invalid: authorization number invalid.', $reply->getContent());

            return;
        }

        self::fail('The exception is expected');
    }

    public function testExecuteShouldThrow400BecauseInvalidSignature()
    {
        $gatewayMock = $this->createGatewayMock();
        $gatewayMock
            ->expects(self::once())
            ->method('execute')
            ->with(self::isInstanceOf(GetHttpRequest::class))
            ->willReturnCallback(function (GetHttpRequest $request) {
                $request->query = [
                    'amount' => 100,
                    'authorization_number' => 'XXXXX',
                    'error_code' => '00000',
                ];
            })
        ;

        $apiMock = $this->createApiMock();
        $apiMock
            ->expects(self::once())
            ->method('verify')
            ->willReturn(false)
        ;

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);
        $action->setApi($apiMock);

        try {
            $action->execute(new Notify([
                'amount' => 100,
            ]));
        } catch (HttpResponse $reply) {
            self::assertSame(400, $reply->getStatusCode());
            self::assertSame('The notification is invalid. Code 1', $reply->getContent());

            return;
        }
    }

    /**
     * @covers \Marem\PayumPaybox\Action\NotifyAction::execute
     */
    public function testExecuteShouldThrow400BecauseQueryAmountDoesNotMatchOneFromModel()
    {
        $gatewayMock = $this->createGatewayMock();
        $gatewayMock
            ->expects(self::once())
            ->method('execute')
            ->with(self::isInstanceOf(GetHttpRequest::class))
            ->willReturnCallback(function (GetHttpRequest $request) {
                $request->query = [
                    'amount' => 100,
                    'authorization_number' => 'XXXXX',
                    'error_code' => '00000',
                ];
            })
        ;

        $apiMock = $this->createApiMock();
        $apiMock
            ->expects(self::once())
            ->method('verify')
            ->willReturn(true)
        ;

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);
        $action->setApi($apiMock);

        try {
            $action->execute(new Notify([
                'amount' => 90,
            ]));
        } catch (HttpResponse $reply) {
            self::assertSame(400, $reply->getStatusCode());
            self::assertSame('Notification invalid: transaction invalid, amount differs from original', $reply->getContent());

            return;
        }
    }

    /**
     * @covers \Marem\PayumPaybox\Action\NotifyAction::execute
     */
    public function testShouldUpdateModelIfValidNotification()
    {
        $gatewayMock = $this->createGatewayMock();
        $gatewayMock
            ->expects(self::once())
            ->method('execute')
            ->with(self::isInstanceOf(GetHttpRequest::class))
            ->willReturnCallback(function (GetHttpRequest $request) {
                $request->query = [
                    'amount' => 100,
                    'authorization_number' => 'XXXXX',
                    'error_code' => '00000',
                ];
            })
        ;

        $apiMock = $this->createApiMock();
        $apiMock
            ->expects(self::once())
            ->method('verify')
            ->willReturn(true)
        ;

        $action = new NotifyAction();
        $action->setGateway($gatewayMock);
        $action->setApi($apiMock);

        $model = new \ArrayObject([
            'amount' => 100,
        ]);

        try {
            $action->execute(new Notify($model));
        } catch (HttpResponse $reply) {
            self::assertEquals(
                [
                    'amount' => 100,
                    'authorization_number' => 'XXXXX',
                    'error_code' => '00000',
                ],
                (array) $model
            );
            self::assertSame(200, $reply->getStatusCode());
            self::assertSame('OK', $reply->getContent());

            return;
        }
    }

    protected function createGatewayMock()
    {
        return $this->createMock(GatewayInterface::class);
    }

    protected function createApiMock()
    {
        return $this->createMock(Api::class, ['verify'], [], '', false);
    }
}
