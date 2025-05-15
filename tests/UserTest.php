<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;
use App\Tests\Helpers\GetTokenTrait;

class TransactionTest extends AbstractTest
{
    use GetTokenTrait;
    
    protected function getFixtures(): array
    {
        return [AppFixtures::class, DataFixtures::class];
    }

    public function testGetTransaction()
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);

        $client->request(
            'GET',
            '/api/v1/transactions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(200);
        $this->assertTrue(is_array($content));
    }

    public function testGetTransactionWithFilter()
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);

        $filter = [
            'filter[type]' => 'payment',
            'filter[skip_expired]' => true
        ];

        $client->request(
            'GET',
            '/api/v1/transactions?' . http_build_query($filter),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertResponseCode(200);
        $this->assertTrue(is_array($content));

        // проверка что нет записи депозита
        $flagNotDeposit = true;
        foreach ($content[0] as $item) {
            if ($item['type'] == 'deposit') {
                $flagNotDeposit = false;
                break;
            }
        }

        $this->assertTrue($flagNotDeposit);
    }
}