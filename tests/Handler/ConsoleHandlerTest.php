<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Handler;

use DateTimeImmutable;
use PhpSoftBox\Logger\Handler\ConsoleHandler;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function fopen;
use function rewind;
use function stream_get_contents;

#[CoversClass(ConsoleHandler::class)]
final class ConsoleHandlerTest extends TestCase
{
    /**
     * Проверяет запись данных в указанный поток.
     */
    #[Test]
    public function writesToProvidedStream(): void
    {
        $stream  = fopen('php://memory', 'w+');
        $handler = new ConsoleHandler($stream);

        $handler->handle($this->record('console'));
        rewind($stream);

        self::assertStringContainsString('console', stream_get_contents($stream));
    }

    private function record(string $message): LogRecord
    {
        return new LogRecord('info', LogLevel::Info, $message, [], new DateTimeImmutable());
    }
}
