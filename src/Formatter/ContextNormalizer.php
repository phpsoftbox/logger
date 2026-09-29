<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Formatter;

use BackedEnum;
use DateTimeInterface;
use JsonSerializable;
use Stringable;
use Throwable;
use UnitEnum;

use function get_resource_type;
use function is_array;
use function is_float;
use function is_infinite;
use function is_nan;
use function is_object;
use function is_resource;
use function json_encode;
use function sprintf;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_PRESERVE_ZERO_FRACTION;

/**
 * Приводит context/extra записи к данным, которые всегда кодируются в JSON: запись в лог не должна падать из-за
 * объекта, ресурса, NAN/INF, не-UTF-8 строки или слишком глубокой вложенности.
 */
final readonly class ContextNormalizer
{
    public function __construct(
        private int $maxDepth = 9,
    ) {
    }

    /**
     * @param list<string>|null $traces Если передан — stack trace исключений собирается сюда, а не в поле `trace`.
     */
    public function normalize(mixed $data, ?array &$traces = null, int $depth = 0): mixed
    {
        if ($depth > $this->maxDepth) {
            return '[max depth]';
        }

        if ($data instanceof Throwable) {
            return $this->normalizeThrowable($data, $traces, $depth);
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->normalize($value, $traces, $depth + 1);
            }

            return $result;
        }

        if (is_object($data)) {
            return match (true) {
                $data instanceof DateTimeInterface => $data->format(DateTimeInterface::RFC3339_EXTENDED),
                $data instanceof JsonSerializable  => $this->normalize($data->jsonSerialize(), $traces, $depth + 1),
                $data instanceof BackedEnum        => $data->value,
                $data instanceof UnitEnum          => $data->name,
                $data instanceof Stringable        => (string) $data,
                default                            => sprintf('[object %s]', $data::class),
            };
        }

        if (is_resource($data)) {
            return sprintf('[resource %s]', get_resource_type($data));
        }

        if (is_float($data) && (is_nan($data) || is_infinite($data))) {
            return is_nan($data) ? 'NAN' : ($data > 0 ? 'INF' : '-INF');
        }

        return $data;
    }

    /**
     * Кодирует нормализованные данные в JSON без исключений: некорректный UTF-8 заменяется на U+FFFD.
     */
    public function encode(mixed $data, int $flags = 0): string
    {
        $json = json_encode(
            $data,
            $flags | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            $this->maxDepth + 3,
        );

        return $json === false ? '"[unencodable]"' : $json;
    }

    /**
     * @param list<string>|null $traces
     * @return array<string, mixed>
     */
    private function normalizeThrowable(Throwable $exception, ?array &$traces, int $depth): array
    {
        $trace = $exception->getTraceAsString();

        $result = [
            'class'   => $exception::class,
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
        ];

        if ($traces === null) {
            $result['trace'] = $trace;
        } elseif ($trace !== '') {
            $traces[] = $trace;
        }

        $previous = $exception->getPrevious();
        if ($previous !== null) {
            $result['previous'] = $this->normalize($previous, $traces, $depth + 1);
        }

        return $result;
    }
}
