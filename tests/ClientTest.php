<?php

namespace HenryEjemuta\Vtung\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HenryEjemuta\Vtung\Client;
use HenryEjemuta\Vtung\VtungException;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private function createClientWithMockResponse(array $responses, &$container = [], $token = 'test_token')
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $history = Middleware::history($container);
        $handlerStack->push($history);

        $client = new Client($token);

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);

        $guzzleClient = new GuzzleClient(['handler' => $handlerStack, 'base_uri' => 'https://vtu.ng/wp-json/']);
        $property->setValue($client, $guzzleClient);

        return $client;
    }

    public function testAuthenticate()
    {
        $mockResponse = new Response(200, [], json_encode(['token' => 'new_jwt_token']));
        $container = [];
        // Create client without token initially
        $client = $this->createClientWithMockResponse([$mockResponse], $container, null);

        $result = $client->authenticate('user', 'pass');

        $this->assertEquals(['token' => 'new_jwt_token'], $result);
        $this->assertEquals('POST', $container[0]['request']->getMethod());
        $this->assertEquals('/wp-json/jwt-auth/v1/token', $container[0]['request']->getUri()->getPath());

        // Verify that internal token is set (via reflection)
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('token');
        $property->setAccessible(true);
        $this->assertEquals('new_jwt_token', $property->getValue($client));
    }

    public function testGetBalance()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success', 'data' => ['balance' => 5000]]));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $result = $client->getBalance();

        $this->assertEquals(['code' => 'success', 'data' => ['balance' => 5000]], $result);
        $this->assertEquals('GET', $container[0]['request']->getMethod());
        $this->assertEquals('/wp-json/api/v2/balance', $container[0]['request']->getUri()->getPath());
    }

    public function testPurchaseAirtime()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success', 'message' => 'ORDER PROCESSING']));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $result = $client->purchaseAirtime('mtn', '08012345678', 100, 'req_123');

        $this->assertEquals(['code' => 'success', 'message' => 'ORDER PROCESSING'], $result);
        $this->assertEquals('POST', $container[0]['request']->getMethod());
        $body = json_decode($container[0]['request']->getBody(), true);
        $this->assertEquals('mtn', $body['service_id']);
        $this->assertEquals(100, $body['amount']);
    }

    public function testGetDataVariations()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success', 'data' => []]));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $result = $client->getDataVariations('mtn');

        $this->assertEquals('GET', $container[0]['request']->getMethod());
        $this->assertEquals('service_id=mtn', $container[0]['request']->getUri()->getQuery());
        $this->assertEquals('/wp-json/api/v2/variations/data', $container[0]['request']->getUri()->getPath());
    }

    public function testPurchaseData()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success']));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $client->purchaseData('mtn', '08012345678', 'var_1', 'req_123');

        $body = json_decode($container[0]['request']->getBody(), true);
        $this->assertEquals('mtn', $body['service_id']);
        $this->assertEquals('var_1', $body['variation_id']);
    }

    public function testVerifyCustomer()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success']));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $client->verifyCustomer('cust_1', 'ikeja-electric', 'prepaid');

        $body = json_decode($container[0]['request']->getBody(), true);
        $this->assertEquals('cust_1', $body['customer_id']);
        $this->assertEquals('ikeja-electric', $body['service_id']);
        $this->assertEquals('prepaid', $body['variation_id']);
    }

    public function testPurchaseElectricity()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success']));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $client->purchaseElectricity('req_1', 'cust_1', 'ikeja', 'prepaid', 1000);

        $body = json_decode($container[0]['request']->getBody(), true);
        $this->assertEquals('req_1', $body['request_id']);
        $this->assertEquals(1000, $body['amount']);
    }

    public function testFundBettingAccount()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success']));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $client->fundBettingAccount('req_1', 'cust_1', 'bet9ja', 500);

        $body = json_decode($container[0]['request']->getBody(), true);
        $this->assertEquals('bet9ja', $body['service_id']);
        $this->assertEquals(500, $body['amount']);
    }

    public function testGetCableVariations()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success', 'data' => []]));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $client->getCableVariations('dstv');

        $this->assertEquals('GET', $container[0]['request']->getMethod());
        $this->assertEquals('service_id=dstv', $container[0]['request']->getUri()->getQuery());
        $this->assertEquals('/wp-json/api/v2/variations/tv', $container[0]['request']->getUri()->getPath());
    }

    public function testPurchaseCableTV()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success']));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $client->purchaseCableTV('req_1', 'iuc_1', 'dstv', 'var_1');

        $body = json_decode($container[0]['request']->getBody(), true);
        $this->assertEquals('dstv', $body['service_id']);
        $this->assertEquals('iuc_1', $body['customer_id']);
    }

    public function testPurchaseEPins()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success']));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $client->purchaseEPins('req_1', 'mtn', 500, 2);

        $body = json_decode($container[0]['request']->getBody(), true);
        $this->assertEquals('mtn', $body['service_id']);
        $this->assertEquals(500, $body['value']);
        $this->assertEquals(2, $body['quantity']);
    }

    public function testRequeryOrder()
    {
        $mockResponse = new Response(200, [], json_encode(['code' => 'success']));
        $container = [];
        $client = $this->createClientWithMockResponse([$mockResponse], $container);

        $client->requeryOrder('req_1');

        $body = json_decode($container[0]['request']->getBody(), true);
        $this->assertEquals('req_1', $body['request_id']);
        $this->assertEquals('/wp-json/api/v2/requery', $container[0]['request']->getUri()->getPath());
    }
}
