<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;

use function array_map;
use function array_shift;
use function count;
use function implode;

use const PHP_EOL;

final class BufferHandler extends AbstractProcessingHandler
{
    /** @var list<LogRecord> */
    private array $buffer = [];

    private ?HandlerInterface $targetHandler;

    /**
     * @param list<ProcessorInterface> $processors
     */
    public function __construct(
        ?HandlerInterface $handler = null,
        private readonly int $bufferSize = 0,
        private readonly bool $flushOnOverflow = true,
        LogLevel $level = LogLevel::Debug,
        FormatterInterface $formatter = new LineFormatter(),
        array $processors = [],
    ) {
        $this->targetHandler = $handler;
        parent::__construct($level, $formatter, $processors);
    }

    public function setHandler(HandlerInterface $handler): void
    {
        $this->targetHandler = $handler;
    }

    public function handle(LogRecord $record): void
    {
        if (!$this->isHandling($record->severity)) {
            return;
        }

        $this->buffer[] = $this->processRecord($record);

        if ($this->bufferSize > 0 && count($this->buffer) >= $this->bufferSize) {
            $this->flushOnOverflow ? $this->flush() : array_shift($this->buffer);
        }
    }

    public function flush(?HandlerInterface $handler = null): void
    {
        if ($this->buffer === []) {
            return;
        }

        $target = $handler ?? $this->targetHandler;

        if ($target !== null) {
            foreach ($this->buffer as $record) {
                if ($target->isHandling($record->severity)) {
                    $target->handle($record);
                }
            }
        }

        $this->buffer = [];
    }

    /**
     * @return list<LogRecord>
     */
    public function getBufferedRecords(): array
    {
        return $this->buffer;
    }

    /**
     * @return list<string>
     */
    public function getFormattedBuffer(): array
    {
        return array_map(fn (LogRecord $record): string => $this->formatter->format($record), $this->buffer);
    }

    public function drain(string $separator = PHP_EOL): string
    {
        if ($this->buffer === []) {
            return '';
        }

        $formatted    = $this->getFormattedBuffer();
        $this->buffer = [];

        return implode($separator, $formatted);
    }

    public function clear(): void
    {
        $this->buffer = [];
    }

    public function close(): void
    {
        if ($this->buffer !== []) {
            $this->flush();
        }

        parent::close();
    }
}
