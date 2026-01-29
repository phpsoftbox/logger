<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Formatter;

use DateTimeImmutable;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(LineFormatter::class)]
final class LineFormatterTest extends TestCase
{
    /**
     * Проверяет базовое форматирование строки лога.
     */
    #[Test]
    public function formatsBasicLine(): void
    {
        $formatter = new LineFormatter();
        $record    = new LogRecord(
            level: 'info',
            severity: LogLevel::Info,
            message: 'Hello',
            context: ['user' => 10],
            datetime: new DateTimeImmutable('2026-02-18T10:00:00+00:00'),
            extra: ['ip' => '127.0.0.1'],
            channel: 'app',
        );

        $line = $formatter->format($record);

        self::assertStringContainsString('[2026-02-18T10:00:00+00:00] INFO: Hello', $line);
        self::assertStringContainsString('"user":10', $line);
        self::assertStringContainsString('"ip":"127.0.0.1"', $line);
    }

    /**
     * Проверяет, что stack trace выводится в несколько строк.
     */
    #[Test]
    public function formatsThrowableWithMultilineTrace(): void
    {
        $formatter = new LineFormatter();
        $error     = new RuntimeException('Boom!');

        $record = new LogRecord(
            level: 'error',
            severity: LogLevel::Error,
            message: 'Failed',
            context: ['exception' => $error],
            datetime: new DateTimeImmutable('2026-02-18T11:00:00+00:00'),
        );

        $line = $formatter->format($record);

        self::assertStringContainsString('[2026-02-18T11:00:00+00:00] ERROR: Failed', $line);
        self::assertStringContainsString('"class":"RuntimeException"', $line);
        self::assertStringContainsString("\n#0 ", $line);
        self::assertStringNotContainsString('"trace"', $line);
    }
}
