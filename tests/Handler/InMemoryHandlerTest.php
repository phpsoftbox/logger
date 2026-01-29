<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Handler;

use DateTimeImmutable;
use PhpSoftBox\Logger\Handler\InMemoryHandler;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryHandler::class)]
final class InMemoryHandlerTest extends TestCase
{
    /**
     * Проверяет, что обработчик сохраняет записи в память и форматирует их.
     */
    #[Test]
    public function storesRecords(): void
    {
        $handler = new InMemoryHandler();
        $record  = new LogRecord('info', LogLevel::Info, 'stored', [], new DateTimeImmutable());

        $handler->handle($record);

        self::assertCount(1, $handler->getRecords());
        self::assertStringContainsString('stored', $handler->getFormatted()[0]);
    }
}
