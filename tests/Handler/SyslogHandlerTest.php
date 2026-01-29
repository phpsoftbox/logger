<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Handler;

use DateTimeImmutable;
use PhpSoftBox\Logger\Handler\SyslogHandler;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyslogHandler::class)]
final class SyslogHandlerTest extends TestCase
{
    /**
     * Проверяет, что обработчик может отправить запись в системный журнал.
     */
    #[Test]
    public function handlesRecordWithoutErrors(): void
    {
        $handler = new SyslogHandler(level: LogLevel::Debug);

        $record = new LogRecord('info', LogLevel::Info, 'syslog', [], new DateTimeImmutable());

        $handler->handle($record);

        $handler->close();
        $this->addToAssertionCount(1);
    }
}
