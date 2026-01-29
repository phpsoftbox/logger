<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Handler;

use InvalidArgumentException;
use PhpSoftBox\Logger\Formatter\FormatterInterface;
use PhpSoftBox\Logger\Formatter\LineFormatter;
use PhpSoftBox\Logger\LogLevel;
use PhpSoftBox\Logger\LogRecord;
use PhpSoftBox\Logger\Processor\ProcessorInterface;
use RuntimeException;

use function dirname;
use function fclose;
use function fflush;
use function file_exists;
use function fopen;
use function fwrite;
use function is_dir;
use function is_resource;
use function mkdir;
use function sprintf;
use function str_starts_with;

use const PHP_EOL;

class StreamHandler extends AbstractProcessingHandler
{
    /** @var resource|null */
    private $stream;

    /**
     * @param list<ProcessorInterface> $processors
     */
    public function __construct(
        private readonly string $path,
        private readonly string $mode = 'a',
        LogLevel $level = LogLevel::Debug,
        FormatterInterface $formatter = new LineFormatter(),
        array $processors = [],
    ) {
        if ($path === '') {
            throw new InvalidArgumentException('Stream path cannot be empty.');
        }

        parent::__construct($level, $formatter, $processors);
    }

    public function handle(LogRecord $record): void
    {
        if (!$this->isHandling($record->severity)) {
            return;
        }

        $processed = $this->processRecord($record);
        $stream    = $this->getStream();
        fwrite($stream, $this->formatter->format($processed) . PHP_EOL);
        fflush($stream);
    }

    public function close(): void
    {
        parent::close();

        if (is_resource($this->stream)) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * @return resource
     */
    private function getStream()
    {
        if ($this->stream === null) {
            $this->ensurePathExists();
            $stream = fopen($this->path, $this->mode);
            if ($stream === false) {
                throw new RuntimeException(sprintf('Unable to open stream "%s".', $this->path));
            }

            $this->stream = $stream;
        }

        return $this->stream;
    }

    private function ensurePathExists(): void
    {
        if (str_starts_with($this->path, 'php://')) {
            return;
        }

        $directory = dirname($this->path);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
            }
        }

        if (!file_exists($this->path)) {
            $handle = @fopen($this->path, 'a');
            if ($handle === false) {
                throw new RuntimeException(sprintf('Unable to create log file "%s".', $this->path));
            }
            fclose($handle);
        }
    }
}
