<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;

class UserTest extends AbstractApiTest
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class, DataFixtures::class];
    }

    public function testCurrentUsersSuccess(): void
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);

        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/users/current',
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
        $this->assertEquals('user@email.example', $content['username']);
        $this->assertArrayHasKey('roles', $content);
        $this->assertArrayHasKey('balance', $content);
    }

    public function testCurrentUsersFail(): void
    {
        $client = $this->createTestClient();

        // Невалидный токен
        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/users/current',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer invalid_token',
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $this->assertResponseCode(401);
        
        // Без токена
        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/users/current',
        );
        $this->assertResponseCode(401);
    }
}