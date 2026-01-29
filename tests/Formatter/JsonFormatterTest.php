<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Formatter;

use DateTimeImmutable;
use PhpSoftBox\Logger\Formatter\JsonFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class JsonFormatterTest extends TestCase
{
    #[Test]
    public function formatsMessageAsJson(): void
    {
        $formatter = new JsonFormatter();
        $record    = new LogRecord(
            level: 'alert',
            severity: LogLevel::Alert,
            message: 'Hurrrraaaaaay!!!!',
            context: ['password' => '[HIDDEN]'],
            datetime: new DateTimeImmutable('2025-12-13T12:25:14+00:00'),
            channel: 'api',
        );

        $line = $formatter->format($record);

        self::assertStringContainsString('[2025-12-13T12:25:14+00:00] ALERT: ', $line);
        self::assertStringContainsString('"message":"Hurrrraaaaaay!!!!"', $line);
        self::assertStringContainsString('"password":"[HIDDEN]"', $line);
    }

    #[Test]
    public function keepsEmbeddedJsonUntouched(): void
    {
        $formatter = new JsonFormatter();
        $record    = new LogRecord(
            level: 'alert',
            severity: LogLevel::Alert,
            message: json_encode(['welcomePack' => 'Hurrrraaaaaay!!!! My password is {password}'], JSON_THROW_ON_ERROR),
            context: ['password' => '[HIDDEN]'],
            datetime: new DateTimeImmutable('2025-12-14T09:09:57+00:00'),
            channel: 'api',
        );

        $line = $formatter->format($record);

        self::assertStringContainsString('"welcomePack":"Hurrrraaaaaay!!!! My password is {password}"', $line);
        self::assertStringNotContainsString('\\"welcomePack', $line);
        self::assertStringContainsString('"context":{"password":"[HIDDEN]"}', $line);
    }
}
