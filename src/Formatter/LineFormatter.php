<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Formatter;

use JsonException;
use PhpSoftBox\Logger\LogRecord;
use Throwable;

use function array_map;
use function get_resource_type;
use function implode;
use function is_array;
use function is_object;
use function is_resource;
use function json_encode;
use function sprintf;
use function strtoupper;
use function strtr;

use const DATE_ATOM;
use const JSON_THROW_ON_ERROR;

final class LineFormatter implements FormatterInterface
{
    public function __construct(
        private readonly string $format = '[%datetime%] %level_name%: %message% %context% %extra%',
        private readonly string $dateFormat = DATE_ATOM,
        private readonly bool $stacktraceMultiline = true,
    ) {
    }

    public function format(LogRecord $record): string
    {
        $traces  = [];
        $context = $this->stringify($record->context, $traces);
        $extra   = $this->stringify($record->extra, $traces);

        $replace = [
            '%datetime%'   => $record->datetime->format($this->dateFormat),
            '%level_name%' => strtoupper($record->level),
            '%message%'    => $record->message,
            '%context%'    => $context,
            '%extra%'      => $extra,
            '%channel%'    => $record->channel ?? 'app',
        ];

        $line = strtr($this->format, $replace);

        if ($this->stacktraceMultiline && $traces !== []) {
            $line .= "\n" . implode("\n", $traces);
        }

        return $line;
    }

    /**
     * @param array<string, mixed> $data
     * @throws JsonException
     */
    private function stringify(array $data, array &$traces): string
    {
        if ($data === []) {
            return '';
        }

        return json_encode($this->normalize($data, $traces), JSON_THROW_ON_ERROR);
    }

    /**
     */
    private function normalize(mixed $data, array &$traces): mixed
    {
        if ($data instanceof Throwable) {
            $trace = $data->getTraceAsString();
            if ($this->stacktraceMultiline && $trace !== '') {
                $traces[] = $trace;
            }

            return [
                'class'   => $data::class,
                'message' => $data->getMessage(),
                'code'    => $data->getCode(),
                'file'    => $data->getFile(),
                'line'    => $data->getLine(),
                ...($this->stacktraceMultiline ? [] : ['trace' => $trace]),
            ];
        }

        if (is_array($data)) {
            return array_map(function ($value) use (&$traces) {
                return $this->normalize($value, $traces);
            }, $data);
        }

        if (is_object($data)) {
            return sprintf('[object %s]', $data::class);
        }

        if (is_resource($data)) {
            return sprintf('[resource %s]', get_resource_type($data) ?: 'unknown');
        }

        return $data;
    }
}
