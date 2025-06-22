<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;

class TransactionTest extends AbstractApiTest
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class, DataFixtures::class];
    }

    public function testGetTransaction(): void
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);
        
        // Совершаем покупку для генерации транзакций
        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        
        $courses = json_decode($client->getResponse()->getContent(), true);
        $affordableCourse = null;
        
        foreach ($courses as $course) {
            if ($course['type'] === 'buy' && $course['price'] < 300) {
                $affordableCourse = $course['code'];
                break;
            }
        }
        
        if (!$affordableCourse) {
            $this->markTestSkipped('No affordable course found');
        }

        $client->request(
            'POST',
            self::BASE_URL . "/api/v1/courses/{$affordableCourse}/pay",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $this->assertResponseCode(200);

        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/transactions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $client->getResponse();
        $this->assertResponseCode(200, $response);
        
        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertNotEmpty($content);
    }

    public function testGetTransactionWithFilter(): void
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);
        
        // Совершаем покупку для генерации транзакций
        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        
        $courses = json_decode($client->getResponse()->getContent(), true);
        $affordableCourse = null;
        
        foreach ($courses as $course) {
            if ($course['type'] === 'buy' && $course['price'] < 300) {
                $affordableCourse = $course['code'];
                break;
            }
        }
        
        if (!$affordableCourse) {
            $this->markTestSkipped('No affordable course found');
        }

        $client->request(
            'POST',
            self::BASE_URL . "/api/v1/courses/{$affordableCourse}/pay",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $this->assertResponseCode(200);

        $filter = [
            'filter[type]' => 'payment',
            'filter[skip_expired]' => 'true'
        ];

        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/transactions?' . http_build_query($filter),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $client->getResponse();
        $this->assertResponseCode(200, $response);
        
        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        
        foreach ($content as $transaction) {
            $this->assertEquals('payment', $transaction['type']);
        }
    }
}