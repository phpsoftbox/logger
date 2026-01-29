<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests;

use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Logger\LoggerMiddleware;
use PhpSoftBox\Logger\Tests\Fixtures\LoggerSpy;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class LoggerMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что лог содержит метод, путь, статус и длительность.
     */
    public function testLogsRequestContext(): void
    {
        $logger = new LoggerSpy();

        $middleware = new LoggerMiddleware($logger);

        $request = new ServerRequest('GET', 'https://example.com/users');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                \Psr\Http\Message\ServerRequestInterface $request,
            ): \Psr\Http\Message\ResponseInterface {
                return new Response(201);
            }
        };

        $middleware->process($request, $handler);

        $this->assertCount(1, $logger->records);
        [$level, $message, $context] = $logger->records[0];

        $this->assertSame('info', $level);
        $this->assertSame('HTTP {method} {path} {status} {duration}ms', $message);
        $this->assertSame('GET', $context['method']);
        $this->assertSame('/users', $context['path']);
        $this->assertSame(201, $context['status']);
        $this->assertIsInt($context['duration']);
    }
}
