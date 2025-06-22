<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;

abstract class AbstractApiTest extends AbstractTest
{
    protected const BASE_URL = 'http://billing.study-on.local:82';
    
    protected function getToken($client, $username = 'user@email.example', $password = 'password')
    {
        $client->request(
            'POST',
            self::BASE_URL . '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => $username,
                'password' => $password
            ])
        );

        $response = $client->getResponse();
        $this->assertResponseCode(200, $response);
        $content = json_decode($response->getContent(), true);
        
        if (!isset($content['token'])) {
            $this->fail('Token not found in response: ' . json_encode($content));
        }
        
        return $content['token'];
    }
}