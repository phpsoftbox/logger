<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Configurator;

use InvalidArgumentException;
use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\JsonFormatter;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\Handler\BufferHandler;
use PhpSoftBox\Logger\Handler\HandlerInterface;
use PhpSoftBox\Logger\Handler\StreamHandler;
use PhpSoftBox\Logger\Logger;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;
use Psr\Log\LoggerInterface;

use function class_exists;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;
use function sys_get_temp_dir;

use const DATE_ATOM;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class LoggerFactory implements LoggerFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {
    }

    public function create(string $channel = 'app'): LoggerInterface
    {
        $channelConfig = $this->config['channels'][$channel] ?? [];

        $handlers   = $this->createHandlers($channelConfig['handlers'] ?? []);
        $processors = $this->createProcessors($channelConfig['processors'] ?? []);

        return new Logger(
            name: $channelConfig['name'] ?? $channel,
            handlers: $handlers,
            processors: $processors,
        );
    }

    /**
     * @param array<int, HandlerInterface|array<string, mixed>> $configs
     * @return list<HandlerInterface>
     */
    private function createHandlers(array $configs): array
    {
        if ($configs === []) {
            return [new StreamHandler(path: sys_get_temp_dir() . '/phpsoftbox.log')];
        }

        $handlers = [];

        foreach ($configs as $config) {
            if ($config instanceof HandlerInterface) {
                $handlers[] = $config;
                continue;
            }

            if (!is_array($config)) {
                throw new InvalidArgumentException('Handler configuration must be an array or HandlerInterface instance.');
            }

            $type       = $config['type'] ?? 'stream';
            $level      = isset($config['level']) ? LogLevel::fromPsrLevel($config['level']) : LogLevel::Debug;
            $formatter  = $this->createFormatter($config['formatter'] ?? null);
            $processors = $this->createProcessors($config['processors'] ?? []);

            $handlers[] = match ($type) {
                'stream' => new StreamHandler(
                    path: $config['path'] ?? sys_get_temp_dir() . '/phpsoftbox.log',
                    mode: $config['mode'] ?? 'a',
                    level: $level,
                    formatter: $formatter,
                    processors: $processors,
                ),
                'buffer' => $this->createBufferHandler($config, $level, $formatter, $processors),
                default  => throw new InvalidArgumentException(sprintf('Unknown handler type "%s"', (string) $type)),
            };
        }

        return $handlers;
    }

    /**
     * @param array<string, mixed>|HandlerInterface|null $handlerConfig
     */
    private function resolveInnerHandler(null|array|HandlerInterface $handlerConfig): ?HandlerInterface
    {
        if ($handlerConfig instanceof HandlerInterface) {
            return $handlerConfig;
        }

        if (is_array($handlerConfig)) {
            return $this->createHandlers([$handlerConfig])[0] ?? null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createBufferHandler(
        array $config,
        LogLevel $level,
        FormatterInterface $formatter,
        array $processors,
    ): BufferHandler {
        $innerHandler = $this->resolveInnerHandler($config['handler'] ?? null);

        return new BufferHandler(
            handler: $innerHandler,
            bufferSize: $config['buffer_size'] ?? 0,
            flushOnOverflow: $config['flush_on_overflow'] ?? true,
            level: $level,
            formatter: $formatter,
            processors: $processors,
        );
    }

    /**
     * @param array<string, mixed>|string|FormatterInterface|null $config
     */
    private function createFormatter(array|string|FormatterInterface|null $config): FormatterInterface
    {
        if ($config instanceof FormatterInterface) {
            return $config;
        }

        if ($config === null || $config === [] || $config === 'line') {
            return new LineFormatter();
        }

        if ($config === 'json') {
            return new JsonFormatter();
        }

        if (is_array($config)) {
            $type = $config['type'] ?? 'line';

            return match ($type) {
                'json'  => new JsonFormatter($config['flags'] ?? JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                default => new LineFormatter(
                    format: $config['format'] ?? '[%datetime%] %level_name%: %message%',
                    dateFormat: $config['date_format'] ?? DATE_ATOM,
                    stacktraceMultiline: (bool) ($config['stacktrace_multiline'] ?? $config['stacktraceMultiline'] ?? true),
                ),
            };
        }

        throw new InvalidArgumentException('Invalid formatter configuration.');
    }

    /**
     * @param array<int, callable|ProcessorInterface|string> $configs
     * @return list<ProcessorInterface>
     */
    private function createProcessors(array $configs): array
    {
        $processors = [];

        foreach ($configs as $config) {
            if ($config instanceof ProcessorInterface) {
                $processors[] = $config;
                continue;
            }

            if (is_callable($config)) {
                $processors[] = new class ($config) implements ProcessorInterface {
                    public function __construct(
                        private $callable,
                    ) {
                    }

                    public function __invoke(LogRecord $record): LogRecord
                    {
                        return ($this->callable)($record);
                    }
                };
                continue;
            }

            if (is_string($config) && class_exists($config)) {
                $processor = new $config();

                if (!$processor instanceof ProcessorInterface) {
                    throw new InvalidArgumentException(sprintf('Processor "%s" must implement ProcessorInterface', $config));
                }
                $processors[] = $processor;
                continue;
            }

            throw new InvalidArgumentException('Invalid processor configuration provided.');
        }

        return $processors;
    }
}
