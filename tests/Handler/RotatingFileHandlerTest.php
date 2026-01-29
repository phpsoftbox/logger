<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Handler;

use DateTimeImmutable;
use PhpSoftBox\Logger\Handler\RotatingFileHandler;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(RotatingFileHandler::class)]
final class RotatingFileHandlerTest extends TestCase
{
    /**
     * Проверяет ротацию файла при превышении лимита размера.
     */
    #[Test]
    public function rotatesWhenFileExceedsSize(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rotate');
        self::assertIsString($path);

        file_put_contents($path, str_repeat('A', 100));

        $handler = new RotatingFileHandler($path, maxFiles: 2, maxBytes: 50);

        $handler->handle($this->record('rotation-test'));

        self::assertFileExists($path . '.1');

        $handler->close();
        unlink($path);
        unlink($path . '.1');
    }

    private function record(string $message): LogRecord
    {
        return new LogRecord('info', LogLevel::Info, $message, [], new DateTimeImmutable());
    }
}
