<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Formatter;

use DateTimeImmutable;
use PhpSoftBox\Logger\Formatter\JsonFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(JsonFormatter::class)]
#[CoversMethod(JsonFormatter::class, 'format')]
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

    /**
     * Проверим, что сообщение с некорректным UTF-8 не роняет запись в лог.
     *
     * @see JsonFormatter::format()
     */
    #[Test]
    public function invalidUtf8MessageIsSubstituted(): void
    {
        $formatter = new JsonFormatter();
        $record    = new LogRecord(
            level: 'error',
            severity: LogLevel::Error,
            message: "bad \xFF byte",
            context: [],
            datetime: new DateTimeImmutable('2025-12-13T12:25:14+00:00'),
        );

        $line = $formatter->format($record);

        self::assertStringContainsString("\"message\":\"bad \u{FFFD} byte\"", $line);
    }

    /**
     * Проверим, что исключение в контексте сериализуется (класс, сообщение), а не превращается в пустой объект.
     *
     * @see JsonFormatter::format()
     */
    #[Test]
    public function throwableInContextIsSerialized(): void
    {
        $formatter = new JsonFormatter();
        $record    = new LogRecord(
            level: 'error',
            severity: LogLevel::Error,
            message: 'Failed',
            context: ['exception' => new RuntimeException('Boom!')],
            datetime: new DateTimeImmutable('2025-12-13T12:25:14+00:00'),
        );

        $line = $formatter->format($record);

        self::assertStringContainsString('"class":"RuntimeException"', $line);
        self::assertStringContainsString('"message":"Boom!"', $line);
    }
}
