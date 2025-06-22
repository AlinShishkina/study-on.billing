<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\DataFixtures;

class CourseTest extends AbstractApiTest
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class, DataFixtures::class];
    }

    public function testGetCourses()
    {
        $client = $this->createTestClient();
        
        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        
        $response = $client->getResponse();
        $this->assertResponseCode(200, $response);
        
        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertNotEmpty($content);
    }

    public function testGetCourse()
    {
        $client = $this->createTestClient();
        
        // Получаем существующий курс
        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        
        $courses = json_decode($client->getResponse()->getContent(), true);
        $courseCode = $courses[0]['code'];
        
        $client->request(
            'GET',
            self::BASE_URL . "/api/v1/courses/{$courseCode}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        
        $response = $client->getResponse();
        $this->assertResponseCode(200, $response);
        
        $content = json_decode($response->getContent(), true);
        $this->assertEquals($courseCode, $content['code']);
    }
    
    public function testBuyCourseFail()
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);
        
        // Получаем бесплатный курс
        $client->request(
            'GET',
            self::BASE_URL . '/api/v1/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        
        $courses = json_decode($client->getResponse()->getContent(), true);
        $freeCourse = null;
        $expensiveCourse = null;
        
        foreach ($courses as $course) {
            if ($course['type'] === 'free') {
                $freeCourse = $course['code'];
            }
            if ($course['type'] === 'buy' && $course['price'] > 1000) {
                $expensiveCourse = $course['code'];
            }
        }
        
        if (!$freeCourse) {
            $this->markTestSkipped('No free course found');
        }
        
        if (!$expensiveCourse) {
            $this->markTestSkipped('No expensive course found');
        }

        // Бесплатный курс
        $client->request(
            'POST',
            self::BASE_URL . "/api/v1/courses/{$freeCourse}/pay",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $this->assertResponseCode(400); 
        
        // Недостаточно средств
        $client->request(
            'POST',
            self::BASE_URL . "/api/v1/courses/{$expensiveCourse}/pay",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $this->assertResponseCode(406);
        
        // Несуществующий курс
        $client->request(
            'POST',
            self::BASE_URL . "/api/v1/courses/invalid-course/pay",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $this->assertResponseCode(400);
    }

    public function testBuyCourseSuccess()
    {
        $client = $this->createTestClient();
        $token = $this->getToken($client);
        
        // Получаем доступный курс
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
        
        $response = $client->getResponse();
        $this->assertResponseCode(200, $response);
        
        $content = json_decode($response->getContent(), true);
        $this->assertTrue($content['success']);
    }
}