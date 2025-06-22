<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;

class AuthTest extends AbstractApiTest
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class, DataFixtures::class];
    }

    public function testSuccessAuth()
    {
        $client = $this->createTestClient();
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user@email.example',
                'password' => 'password'
            ])
        );
        
        $response = $client->getResponse();
        $this->assertResponseCode(200, $response);
        
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $content);
    }

    public function testFailAuth()
    {
        $client = $this->createTestClient();

        // Несуществующий пользователь
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'nonexistent@example.com',
                'password' => 'password'
            ])
        );
        $this->assertResponseCode(401);
        
        // Неверный пароль
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user@email.example',
                'password' => 'wrong_password'
            ])
        );
        $this->assertResponseCode(401);
    }
 
    public function testSuccessRegister()
    {
        $client = $this->createTestClient();
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'newuser@example.com',
                'password' => 'password'
            ])
        );
        
        $response = $client->getResponse();
        $this->assertResponseCode(201, $response);
        
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $content);
    }
 
    public function testFailRegister()
    {
        $client = $this->createTestClient();

        // Существующий email
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user@email.example',
                'password' => 'password'
            ])
        );
        $this->assertResponseCode(400);
        
        // Пустой email
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => '',
                'password' => 'password'
            ])
        );
        $this->assertResponseCode(400);
        
        // Пустой пароль
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user3@example.com',
            ])
        );
        $this->assertResponseCode(400);
        
        // Короткий пароль
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user4@example.com',
                'password' => '123'
            ])
        );
        $this->assertResponseCode(400);
    }
}