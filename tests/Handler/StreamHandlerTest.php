<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Handler;

use DateTimeImmutable;
use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Handler\StreamHandler;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

#[CoversClass(StreamHandler::class)]
final class StreamHandlerTest extends TestCase
{
    /**
     * Проверяет, что обработчик записывает отформатированные данные в файл.
     */
    #[Test]
    public function writesFormattedRecordToFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'logger');
        self::assertIsString($tempFile);

        $handler = new StreamHandler(
            path: $tempFile,
            formatter: new class () implements FormatterInterface {
                public function format(LogRecord $record): string
                {
                    return 'formatted-' . $record->message;
                }
            },
            processors: [new class () implements ProcessorInterface {
                public function __invoke(LogRecord $record): LogRecord
                {
                    return $record->withMessage('processed');
                }
            }],
        );

        $handler->handle($this->record());
        $handler->close();

        $contents = file_get_contents($tempFile);
        self::assertStringContainsString('formatted-processed', (string) $contents);
        unlink($tempFile);
    }

    /**
     * Проверяет, что обработчик создаёт недостающие директории и файл.
     */
    #[Test]
    public function createsMissingPath(): void
    {
        $tempDir = sys_get_temp_dir() . '/logger-path-' . uniqid();
        $path    = $tempDir . '/logs/app.log';

        $handler = new StreamHandler(path: $path);

        $handler->handle($this->record());
        $handler->close();

        self::assertFileExists($path);

        unlink($path);
        rmdir($tempDir . '/logs');
        rmdir($tempDir);
    }

    private function record(): LogRecord
    {
        return new LogRecord('info', LogLevel::Info, 'initial', [], new DateTimeImmutable());
    }
}
