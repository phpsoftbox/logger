<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Formatter;

use PhpSoftBox\Logger\LogRecord;

use function implode;
use function strtoupper;
use function strtr;

use const DATE_ATOM;

final class LineFormatter implements FormatterInterface
{
    public const string DEFAULT_FORMAT = '[%datetime%] %level_name%: %message% %context% %extra%';

    private readonly ContextNormalizer $normalizer;

    public function __construct(
        private readonly string $format = self::DEFAULT_FORMAT,
        private readonly string $dateFormat = DATE_ATOM,
        private readonly bool $stacktraceMultiline = true,
    ) {
        $this->normalizer = new ContextNormalizer();
    }

    public function format(LogRecord $record): string
    {
        $traces  = $this->stacktraceMultiline ? [] : null;
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

        if ($traces !== null && $traces !== []) {
            $line .= "\n" . implode("\n", $traces);
        }

        return $line;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>|null $traces
     */
    private function stringify(array $data, ?array &$traces): string
    {
        if ($data === []) {
            return '';
        }

        return $this->normalizer->encode($this->normalizer->normalize($data, $traces));
    }
}
