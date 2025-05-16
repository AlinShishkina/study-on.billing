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

    public function testGetTransaction(): void
    {
        $client = self::createTestClient();
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

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertResponseOk($response);
        $this->assertIsArray($content);
    }

    public function testGetTransactionWithFilter(): void
    {
        $client = self::createTestClient();
        $token = $this->getToken($client);

        $filter = [
            'filter[type]' => 'payment',
            'filter[skip_expired]' => 'true' // передаем как строку, т.к. в URL
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

        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertResponseOk($response);
        $this->assertIsArray($content);

        // Проверка, что нет транзакций с типом 'deposit'
        $flagNotDeposit = true;
        foreach ($content as $item) {
            if (isset($item['type']) && $item['type'] === 'deposit') {
                $flagNotDeposit = false;
                break;
            }
        }

        $this->assertTrue($flagNotDeposit, 'В ответе не должно быть транзакций типа deposit');
    }
}
