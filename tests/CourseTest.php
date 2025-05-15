<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;
use App\Tests\Helpers\GetTokenTrait;

class CourseTest extends AbstractTest
{
    use GetTokenTrait;
    
    protected function getFixtures(): array
    {
        return [AppFixtures::class, DataFixtures::class];
    }

    public function testGetCourses()
    {
        $client = $this->createTestClient();

        $client->request(
            'GET',
            '/api/v1/courses',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(200);
        $this->assertTrue(is_array($content));
    }

    public function testGetCourse()
    {
        $client = $this->createTestClient();

        $client->request(
            'GET',
            '/api/v1/courses/php',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(200);
        $this->assertTrue(is_array($content));
        $this->assertTrue(array_key_exists('code', $content));
        $this->assertTrue(array_key_exists('price', $content));
        $this->assertTrue(array_key_exists('type', $content));
        $this->assertTrue($content['type'] == 'free');
    }

    
    public function testBuyCourseFail()
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);

        $codes = ['php-1', 'php', 'js', 'swift'];
        $messages = [
            'Курс не найден',
            'Курс бесплатный. Оплата не требуется.',
            'Доступ к курсу актуален. Оплата не требуется.',
            'На счету недостаточно средств',
        ];

        foreach ($codes as $key => $code) {
            $client->request(
                'POST',
                '/api/v1/courses/' . $code . '/pay',
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                    'CONTENT_TYPE' => 'application/json',
                ]
            );

            $response = $client->getResponse();
            $status = $response->getStatusCode();
            $content = json_decode($response->getContent(), true);

            $keyError = $status == 406 ? 'payment' : 'course';

            $this->assertTrue($status == 400 || $status == 406);
            $this->assertTrue(array_key_exists('errors', $content));
            $this->assertTrue($content['errors'][$keyError] == $messages[$key]);
        }
    }

    public function testBuyCourseSuccess()
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);

        $client->request(
            'POST',
            '/api/v1/courses/ruby/pay',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $client->getResponse();
        $status = $response->getStatusCode();

        $this->assertTrue($status == 200);
    }
}