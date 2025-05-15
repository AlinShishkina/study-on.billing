<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;

class AuthTest extends AbstractTest
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class, DataFixtures::class];
    }

    // успешная авторизация
    public function testSuccessAuth()
    {
        $client = $this->createTestClient();
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
        $this->assertResponseCode(200);
        $this->assertTrue(array_key_exists('token', $content));
    }

    // неудачная авторизация
    public function testFailAuth()
    {
        $client = $this->createTestClient();

        // несуществующий логин
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user@email.example1',
                'password' => 'user@email.example'
            ])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(401);
        $this->assertEquals('Invalid credentials.', $content['message']);
        
        // неверный пароль
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user@email.example',
                'password' => 'user@email.example1'
            ])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(401);
        $this->assertEquals('Invalid credentials.', $content['message']);
    }
 
    // успешная регистрация
    public function testSuccessRegister()
    {
        $client = $this->createTestClient();
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user2@email.example',
                'password' => 'user2@email.example'
            ])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(201);
        $this->assertTrue(array_key_exists('token', $content));
        $this->assertTrue(array_key_exists('roles', $content));
        $this->assertTrue(in_array('ROLE_USER', $content['roles']));
    }
 
    // неудачная регистрация
    public function testFailRegister()
    {
        $client = $this->createTestClient();

        // существующая почта
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user@email.example',
                'password' => 'user2@email.example'
            ])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(400);
        $this->assertEquals(
            'Email должен быть уникальным.',
            $content['errors']['username']
        );
        
        // пустое поле username
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => '',
                'password' => 'user2@email.example'
            ])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(400);
        $this->assertEquals(
            'Email обязателен к заполнению.',
            $content['errors']['username']
        );
        
        // пустое поле password
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user2@email.example',
            ])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(400);
        $this->assertEquals(
            'Пароль обязателен к заполнению.',
            $content['errors']['password']
        );
        
        // пароль короче 6 символов
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'user3@email.example',
                'password' => 'e13'
            ])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(400);
        $this->assertEquals(
            'Минимальная длинна пароля: 6',
            $content['errors']['password']
        );
    }
}