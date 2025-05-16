<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;

class UserTest extends AbstractTest
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class, DataFixtures::class];
    }

    private function getToken($client)
    {
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user@email.example',
                'password' => 'user@email.example'
            ])
        );

        $content = json_decode($client->getResponse()->getContent(), true);

        return $content['token'];
    }

    // успешное получение текущего пользователя
    public function testCurrentUsersSuccess(): void
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);

        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertResponseCode(200);
        $this->assertEquals('user@email.example', $content['username']);
        $this->assertTrue(array_key_exists('roles', $content));
        $this->assertTrue(array_key_exists('balance', $content));
    }

    // неуданое получение текущего пользователя
    // по невалидному токену или без него
    public function testCurrentUsersFail(): void
    {
        $client = $this->createTestClient();

        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer 123123' ,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertResponseCode(401);
        $this->assertEquals('Invalid JWT Token', $content['message']);
    
        $client->request(
            'GET',
            '/api/v1/users/current',
        );

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertResponseCode(401);
        $this->assertEquals('JWT Token not found', $content['message']);
    }
}