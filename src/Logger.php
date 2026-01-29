<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger;

use DateTimeImmutable;
use PhpSoftBox\Logger\Handler\HandlerInterface;
use PhpSoftBox\Logger\Handler\StreamHandler;
use PhpSoftBox\Logger\Processor\ProcessorInterface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

use function array_map;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function strtolower;
use function strtr;
use function sys_get_temp_dir;
use function trim;

final class Logger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<HandlerInterface> */
    private array $handlers;

    /** @var list<ProcessorInterface> */
    private array $processors;

    private string $name;

    /**
     * Конструктор логгера с возможностью задать имя канала, обработчики и процессоры.
     *
     * @param string $name Имя канала, отображаемое в log record.
     * @param list<HandlerInterface> $handlers Пользовательские обработчики, иначе создаётся StreamHandler.
     * @param list<ProcessorInterface> $processors Глобальные процессоры, применяемое перед отправкой записи.
     */
    public function __construct(
        string $name = 'app',
        array $handlers = [],
        array $processors = [],
    ) {
        $this->name     = $name;
        $this->handlers = $handlers === []
            ? [new StreamHandler(path: sys_get_temp_dir() . '/phpsoftbox.log')]
            : $handlers;
        $this->processors = $processors;
    }

    /**
     * Добавляет обработчик в стек.
     */
    public function pushHandler(HandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Добавляет глобальный процессор.
     */
    public function pushProcessor(ProcessorInterface $processor): void
    {
        $this->processors[] = $processor;
    }

    /**
     * Выполняет логирование на заданном уровне с интерполяцией контекста.
     *
     * @param LogLevel|string $level Уровень, совместимый с PSR-3 или enum LogLevel.
     * @param string|Stringable $message Сообщение или объект, приводимый к строке.
     * @param array<string, mixed> $context Контекст для интерполяции и форматтеров.
     */
    public function log($level, $message, array $context = []): void
    {
        $psrLevel = $this->normalizeLevel($level);
        $record   = new LogRecord(
            level: $psrLevel,
            severity: LogLevel::fromPsrLevel($psrLevel),
            message: (string) $message,
            context: $context,
            datetime: new DateTimeImmutable(),
            channel: $this->name,
        );

        $record = $this->applyProcessors($record);
        $record = $record->withMessage($this->interpolate($record->message, $record->context));

        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($record->severity)) {
                $handler->handle($record);
            }
        }
    }

    /**
     * Приводит уровень к строковому представлению PSR-3.
     *
     * @param mixed $level Уровень в виде enum строки.
     */
    private function normalizeLevel(mixed $level): string
    {
        if ($level instanceof LogLevel) {
            return $level->toPsrLevel();
        }

        if (!is_string($level)) {
            throw new InvalidArgumentException('Log level must be a string or PhpSoftBox\\Logger\\LogLevel enum.');
        }

        return strtolower($level);
    }

    /**
     * Подставляет значения контекста в сообщение формата {key}.
     *
     * @param array<string, mixed> $context Пары ключ/значение для подстановки.
     */
    private function interpolate(string $message, array $context): string
    {
        if ($context === []) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if ($value instanceof Stringable ||
                is_scalar($value) ||
                $value === null) {
                $replace['{' . $key . '}'] = $this->stringify($value);
            }
        }

        return $replace === [] ? $message : strtr($message, $replace);
    }

    /**
     * Приводит единичное значение к строке для интерполяции.
     */
    private function stringify(Stringable|string|int|float|bool|null $value): string
    {
        return match (true) {
            $value instanceof Stringable => (string) $value,
            is_string($value)            => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            default         => 'null',
        };
    }

    /**
     * Пропускает запись через цепочку глобальных процессоров.
     */
    private function applyProcessors(LogRecord $record): LogRecord
    {
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }

        return $record;
    }

    /**
     * Возвращает текущее имя канала.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает стек обработчиков.
     *
     * @return list<HandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Возвращает список глобальных процессоров.
     *
     * @return list<ProcessorInterface>
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }

    /**
     * Создаёт клон логгера с другим именем канала и клонированными обработчиками.
     */
    public function withChannel(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Channel name cannot be empty.');
        }

        $clone           = clone $this;
        $clone->name     = $name;
        $clone->handlers = array_map(static fn (HandlerInterface $handler) => clone $handler, $this->handlers);

        return $clone;
    }
}
