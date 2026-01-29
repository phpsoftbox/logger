<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Handler;

use DateTimeImmutable;
use PhpSoftBox\Logger\Handler\BufferHandler;
use PhpSoftBox\Logger\Handler\HandlerInterface;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferHandler::class)]
final class BufferHandlerTest extends TestCase
{
    /**
     * Проверяет, что при переполнении буфера записи сбрасываются во вложенный обработчик.
     */
    #[Test]
    public function bufferFlushesOnOverflow(): void
    {
        $target  = $this->createSpyingHandler();
        $handler = new BufferHandler($target, bufferSize: 2);

        $handler->handle($this->createRecord('first'));
        self::assertSame([], $target->handled);

        $handler->handle($this->createRecord('second'));
        self::assertCount(2, $target->handled);
    }

    /**
     * Проверяет корректность метода drain и очистку буфера.
     */
    #[Test]
    public function drainReturnsFormattedBuffer(): void
    {
        $handler = new BufferHandler(bufferSize: 10);

        $handler->handle($this->createRecord('one'));
        $handler->handle($this->createRecord('two'));

        $drained = $handler->drain(' | ');
        self::assertStringContainsString('one', $drained);
        self::assertStringContainsString('two', $drained);
        self::assertSame('', $handler->drain());
    }

    /**
     * Проверяет, что close вызывает автоматический flush.
     */
    #[Test]
    public function closeFlushesPendingRecords(): void
    {
        $target  = $this->createSpyingHandler();
        $handler = new BufferHandler($target, bufferSize: 10);

        $handler->handle($this->createRecord('to-flush'));

        $handler->close();
        self::assertCount(1, $target->handled);
    }

    private function createSpyingHandler(): HandlerInterface
    {
        return new class () implements HandlerInterface {
            /** @var list<LogRecord> */
            public array $handled = [];

            public function isHandling(LogLevel $level): bool
            {
                return true;
            }

            public function handle(LogRecord $record): void
            {
                $this->handled[] = $record;
            }

            public function close(): void
            {
            }
        };
    }

    private function createRecord(string $message, LogLevel $level = LogLevel::Info): LogRecord
    {
        return new LogRecord(
            level: $level->toPsrLevel(),
            severity: $level,
            message: $message,
            context: [],
            datetime: new DateTimeImmutable(),
        );
    }
}
