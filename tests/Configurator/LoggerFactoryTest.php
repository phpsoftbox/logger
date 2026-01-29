<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Configurator;

use PhpSoftBox\Logger\Configurator\LoggerFactory;
use PhpSoftBox\Logger\Handler\BufferHandler;
use PhpSoftBox\Logger\Handler\NullHandler;
use PhpSoftBox\Logger\Handler\StreamHandler;
use PhpSoftBox\Logger\Logger;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;
use PhpSoftBox\Logger\Tests\Utils\SpyFormatter;
use PhpSoftBox\Logger\Tests\Utils\SpyHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function count;
use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(LoggerFactory::class)]
final class LoggerFactoryTest extends TestCase
{
    /**
     * Проверяет создание логгера с кастомным именем канала и StreamHandler.
     */
    #[Test]
    public function createsLoggerWithCustomChannel(): void
    {
        $factory = new LoggerFactory([
            'channels' => [
                'custom' => [
                    'name'     => 'custom-channel',
                    'handlers' => [
                        [
                            'type'  => 'stream',
                            'path'  => sys_get_temp_dir() . '/custom.log',
                            'level' => 'info',
                        ],
                    ],
                ],
            ],
        ]);

        $logger = $factory->create('custom');

        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame('custom-channel', $logger->getName());
        self::assertInstanceOf(StreamHandler::class, $logger->getHandlers()[0]);
    }

    /**
     * Проверяет сборку BufferHandler из конфигурации.
     */
    #[Test]
    public function createsBufferHandlerFromConfig(): void
    {
        $factory = new LoggerFactory([
            'channels' => [
                'buffered' => [
                    'handlers' => [
                        [
                            'type'        => 'buffer',
                            'buffer_size' => 10,
                            'handler'     => [
                                'type' => 'stream',
                                'path' => sys_get_temp_dir() . '/nested.log',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $logger = $factory->create('buffered');

        self::assertInstanceOf(BufferHandler::class, $logger->getHandlers()[0]);
    }

    /**
     * Проверяет создание процессора по имени класса.
     */
    #[Test]
    public function processorStringClassInstantiation(): void
    {
        $factory = new LoggerFactory([
            'channels' => [
                'with-processor' => [
                    'processors' => [TestProcessor::class],
                ],
            ],
        ]);

        $logger = $factory->create('with-processor');

        self::assertCount(1, $logger->getProcessors());
        self::assertInstanceOf(TestProcessor::class, $logger->getProcessors()[0]);
    }

    /**
     * Проверяет, что фабрика может принимать готовые инстансы обработчиков.
     */
    #[Test]
    public function usesProvidedHandlerInstances(): void
    {
        $handler = new NullHandler();

        $factory = new LoggerFactory([
                    'channels' => [
                        'instanced' => [
                            'handlers' => [$handler],
                        ],
                    ],
                ]);

        $logger = $factory->create('instanced');

        self::assertSame($handler, $logger->getHandlers()[0]);
    }

    /**
     * Проверяет, что объект форматтера корректно применяется к обработчику.
     */
    #[Test]
    public function supportsFormatterObjects(): void
    {
        $formatter = new SpyFormatter();
        $tempFile  = tempnam(sys_get_temp_dir(), 'logger');
        self::assertIsString($tempFile);

        $factory = new LoggerFactory([
            'channels' => [
                'with-formatter' => [
                    'handlers' => [[
                        'type'      => 'stream',
                        'path'      => $tempFile,
                        'formatter' => $formatter,
                    ]],
                ],
            ],
        ]);

        $logger = $factory->create('with-formatter');
        $logger->info('formatted message');

        self::assertSame(1, count($formatter->records));
        self::assertStringContainsString('formatted message', $formatter->formattedMessages[0]);
        unlink($tempFile);
    }

    /**
     * Проверяет, что LineFormatter принимает опцию stacktrace_multiline из конфигурации.
     */
    #[Test]
    public function supportsLineFormatterStacktraceMultilineConfig(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'logger');
        self::assertIsString($tempFile);

        $factory = new LoggerFactory([
            'channels' => [
                'errors' => [
                    'handlers' => [[
                        'type'      => 'stream',
                        'path'      => $tempFile,
                        'formatter' => [
                            'type'                 => 'line',
                            'format'               => '[%datetime%] %level_name%: %message% %context%',
                            'stacktrace_multiline' => false,
                        ],
                    ]],
                ],
            ],
        ]);

        $logger = $factory->create('errors');
        $logger->error('boom', ['exception' => new RuntimeException('fail')]);

        $contents = (string) file_get_contents($tempFile);
        unlink($tempFile);

        self::assertStringContainsString('"trace":"#0 ', $contents);
        self::assertStringNotContainsString("\n#0 ", $contents);
    }

    /**
     * Проверяет, что BufferHandler принимает вложенный обработчик в виде объекта.
     */
    #[Test]
    public function acceptsNestedHandlerInstancesForBuffer(): void
    {
        $spy = new SpyHandler();

        $factory = new LoggerFactory([
                    'channels' => [
                        'buffer-object' => [
                            'handlers' => [[
                                'type'        => 'buffer',
                                'buffer_size' => 1,
                                'handler'     => $spy,
                            ]],
                        ],
                    ],
                ]);

        $logger = $factory->create('buffer-object');
        $logger->info('buffered');

        self::assertCount(1, $spy->handled);
        self::assertSame('buffered', $spy->handled[0]->message);
    }
}

final class TestProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record;
    }
}
