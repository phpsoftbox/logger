<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Formatter;

use JsonException;
use PhpSoftBox\Logger\LogRecord;

use function array_key_exists;
use function is_array;
use function json_decode;
use function json_encode;
use function json_validate;
use function strtoupper;
use function strtr;
use function trim;

use const DATE_ATOM;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class JsonFormatter implements FormatterInterface
{
    public function __construct(
        private string $format = '[%datetime%] %level_name%: %message%',
        private int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function format(LogRecord $record): string
    {
        $payload = $this->buildPayload($record);
        $message = $this->encodePayload($payload);

        return strtr($this->format, [
            '%datetime%'   => $record->datetime->format(DATE_ATOM),
            '%level_name%' => strtoupper($record->level),
            '%message%'    => $message,
            '%channel%'    => $record->channel ?? 'app',
        ]);
    }

    /**
     * @param array<string, mixed>|list<mixed> $payload
     * @throws JsonException
     */
    private function encodePayload(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | $this->flags);
        } catch (JsonException $exception) {
            return json_encode([
                'message' => $payload['message'] ?? 'JSON_ENCODING_ERROR',
                'error'   => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR | $this->flags);
        }
    }

    private function buildPayload(LogRecord $record): array
    {
        $decodedMessage = $this->decodeJsonString($record->message);
        if ($decodedMessage !== null) {
            if ($record->context !== [] && !array_key_exists('context', $decodedMessage)) {
                $decodedMessage['context'] = $record->context;
            }

            if ($record->extra !== [] && !array_key_exists('extra', $decodedMessage)) {
                $decodedMessage['extra'] = $record->extra;
            }

            return $decodedMessage;
        }

        return [
            'message' => $record->message,
            'context' => $record->context,
            'extra'   => $record->extra,
        ];
    }

    private function decodeJsonString(string $message): ?array
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return null;
        }

        if (!json_validate($trimmed)) {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }
}
