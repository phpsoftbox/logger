<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Handler;

use DateTimeImmutable;
use PhpSoftBox\Logger\Handler\RotatingFileHandler;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function rename;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(RotatingFileHandler::class)]
#[CoversMethod(RotatingFileHandler::class, 'handle')]
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

    /**
     * Проверим, что после ротации файла другим процессом (FPM, воркеры с общим логом) обработчик переоткрывает файл
     * по исходному пути, а не продолжает писать в переименованный.
     *
     * @see RotatingFileHandler::handle()
     */
    #[Test]
    public function reopensFileRotatedByAnotherProcess(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rotate');
        self::assertIsString($path);

        $handler = new RotatingFileHandler($path, maxFiles: 2, maxBytes: 1_000_000);

        $handler->handle($this->record('first'));

        // Другой процесс ротировал лог: текущий файл переименован.
        rename($path, $path . '.1');

        $handler->handle($this->record('second'));
        $handler->close();

        $current = (string) file_get_contents($path);
        $rotated = (string) file_get_contents($path . '.1');
        unlink($path);
        unlink($path . '.1');

        self::assertStringContainsString('second', $current);
        self::assertStringNotContainsString('second', $rotated);
    }

    private function record(string $message): LogRecord
    {
        return new LogRecord('info', LogLevel::Info, $message, [], new DateTimeImmutable());
    }
}
