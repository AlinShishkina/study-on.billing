<?php

namespace App\Tests\Helpers;

trait GetTokenTrait
{
    public function getToken($client)
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
}