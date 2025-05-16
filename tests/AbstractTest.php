<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractTest extends WebTestCase
{
    protected static ?\Symfony\Bundle\FrameworkBundle\KernelBrowser $client = null;

    // Создание тестового клиента (одиночный экземпляр)
    public static function createTestClient(array $options = [], array $server = []): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        if (self::$client === null) {
            self::$client = static::createClient($options, $server);
        }

        return self::$client;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::$client = static::createTestClient();
        $this->loadFixtures($this->getFixtures());
    }

    final protected function tearDown(): void
    {
        parent::tearDown();
        self::$client = null;
    }

    protected static function getEntityManager()
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    protected function getFixtures(): array
    {
        return [];
    }

    protected function loadFixtures(array $fixtures = []): void
    {
        $loader = new Loader();

        foreach ($fixtures as $fixture) {
            if (!\is_object($fixture)) {
                $fixture = new $fixture(self::getEntityManager()->getConnection());
            }

            if ($fixture instanceof ContainerAwareInterface) {
                $fixture->setContainer(static::getContainer());
            }

            $loader->addFixture($fixture);
        }

        $em = self::getEntityManager();
        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($loader->getFixtures());
    }

    public function assertResponseOk(?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, 'isOk', $message, $type);
    }

    public function assertResponseRedirect(?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, 'isRedirect', $message, $type);
    }

    public function assertResponseNotFound(?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, 'isNotFound', $message, $type);
    }

    public function assertResponseForbidden(?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, 'isForbidden', $message, $type);
    }

    public function assertResponseCode(int $expectedCode, ?Response $response = null, ?string $message = null, string $type = 'text/html'): void
    {
        $this->failOnResponseStatusCheck($response, $expectedCode, $message, $type);
    }

    public function guessErrorMessageFromResponse(Response $response, string $type = 'text/html'): string
    {
        try {
            $crawler = new Crawler();
            $crawler->addContent($response->getContent(), $type);

            if ($crawler->filter('title')->count() === 0) {
                $add = '';
                $content = $response->getContent();

                if ('application/json' === $response->headers->get('Content-Type')) {
                    $data = json_decode($content);
                    if ($data) {
                        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $add = ' ОТФОРМАТИРОВАНО';
                    }
                }
                $title = '[' . $response->getStatusCode() . ']' . $add . ' - ' . $content;
            } else {
                $title = $crawler->filter('title')->text();
            }
        } catch (\Exception $e) {
            $title = $e->getMessage();
        }

        return trim($title);
    }

    private function failOnResponseStatusCheck(?Response $response = null, $func = null, ?string $message = null, string $type = 'text/html'): void
    {
        if ($func === null) {
            $func = 'isOk';
        }

        if ($response === null && self::$client) {
            $response = self::$client->getResponse();
        }

        try {
            if (is_int($func)) {
                $this->assertEquals($func, $response->getStatusCode());
            } else {
                $this->assertTrue($response->{$func}());
            }
            return;
        } catch (\Exception $e) {
            // Ошибка проверки — идём дальше
        }

        $err = $this->guessErrorMessageFromResponse($response, $type);
        if ($message) {
            $message = rtrim($message, '.') . ". ";
        }

        if (is_int($func)) {
            $template = "Ожидался код ответа %s, получен %s.";
            $message .= sprintf($template, $func, $response->getStatusCode());
        } else {
            $template = "Не выполнено условие для ответа [%s]: %s.";
            $funcFormatted = preg_replace('#([a-z])([A-Z])#', '$1 $2', $func);
            $message .= sprintf($template, $response->getStatusCode(), $funcFormatted);
        }

        $maxLength = 100;
        if (mb_strlen($err, 'utf-8') < $maxLength) {
            $message .= " " . $this->makeErrorOneLine($err);
        } else {
            $message .= " " . $this->makeErrorOneLine(mb_substr($err, 0, $maxLength, 'utf-8') . '...');
            $message .= "\n\n" . $err;
        }

        $this->fail($message);
    }

    private function makeErrorOneLine(string $text): string
    {
        return preg_replace('#[\n\r]+#', ' ', $text);
    }
}
