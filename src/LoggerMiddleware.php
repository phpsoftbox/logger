<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

use function microtime;

final class LoggerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $level = 'info',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start    = microtime(true);
        $response = $handler->handle($request);
        $duration = (int) ((microtime(true) - $start) * 1000);

        $this->logger->log($this->level, 'HTTP {method} {path} {status} {duration}ms', [
            'method'   => $request->getMethod(),
            'path'     => $request->getUri()->getPath(),
            'status'   => $response->getStatusCode(),
            'duration' => $duration,
        ]);

        return $response;
    }
}
