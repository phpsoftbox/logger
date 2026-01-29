<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Processor;

use PhpSoftBox\Logger\LogRecord;

use function array_map;
use function in_array;
use function is_array;
use function is_string;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function strtolower;

final class RedactSecretsProcessor implements ProcessorInterface
{
    /** @var list<string> */
    private array $keys;

    public function __construct(
        array $keys = ['password', 'token', 'secret', 'authorization'],
        private readonly string $replacement = '[REDACTED]',
        private readonly bool $caseInsensitive = true,
    ) {
        $this->keys = array_map(
            fn (string $key): string => $this->caseInsensitive ? strtolower($key) : $key,
            $keys,
        );
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->redactArray($record->context);
        $extra   = $this->redactArray($record->extra);

        return $record
            ->withContext($context)
            ->withExtra($extra);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $matchKey = $this->caseInsensitive ? strtolower((string) $key) : (string) $key;
            if (in_array($matchKey, $this->keys, true)) {
                $data[$key] = $this->replacement;
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactArray($value);
                continue;
            }

            if (is_string($value)) {
                $data[$key] = $this->redactString($value);
            }
        }

        return $data;
    }

    private function redactString(string $value): string
    {
        if ($this->keys === []) {
            return $value;
        }

        $patterns = array_map(
            fn (string $key): string => sprintf('/%s/i', preg_quote($key, '/')),
            $this->keys,
        );

        return preg_replace($patterns, $this->replacement, $value) ?? $value;
    }
}
