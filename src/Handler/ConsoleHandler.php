<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;

use function fclose;
use function fopen;
use function fwrite;
use function is_resource;

use const PHP_EOL;

final class ConsoleHandler extends AbstractProcessingHandler
{
    /** @var resource|null */
    private $stream;

    /**
     * @param list<ProcessorInterface> $processors
     */
    public function __construct(
        $stream = null,
        LogLevel $level = LogLevel::Debug,
        FormatterInterface $formatter = new LineFormatter(),
        array $processors = [],
    ) {
        parent::__construct($level, $formatter, $processors);
        $this->stream = $stream ?? fopen('php://stdout', 'w');
    }

    public function handle(LogRecord $record): void
    {
        if (!$this->isHandling($record->severity)) {
            return;
        }

        $processed = $this->processRecord($record);
        fwrite($this->stream, $this->formatter->format($processed) . PHP_EOL);
    }

    public function close(): void
    {
        parent::close();

        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
