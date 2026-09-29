<?php

declare(strict_types=1);

namespace PhpSoftBox\Logger\Tests\Formatter;

use LogicException;
use PhpSoftBox\Logger\Formatter\ContextNormalizer;
use PhpSoftBox\Logger\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function fclose;
use function fopen;
use function json_decode;

use const INF;
use const JSON_THROW_ON_ERROR;
use const NAN;

#[CoversClass(ContextNormalizer::class)]
#[CoversMethod(ContextNormalizer::class, 'normalize')]
#[CoversMethod(ContextNormalizer::class, 'encode')]
final class ContextNormalizerTest extends TestCase
{
    /**
     * Проверим, что некорректный UTF-8 не роняет кодирование, а заменяется на U+FFFD.
     *
     * @see ContextNormalizer::encode()
     */
    #[Test]
    public function invalidUtf8IsSubstituted(): void
    {
        $normalizer = new ContextNormalizer();

        $json = $normalizer->encode($normalizer->normalize(['name' => "caf\xE9", "bad\xFF" => 1]));

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame("caf\u{FFFD}", $decoded['name']);
        self::assertSame(1, $decoded["bad\u{FFFD}"]);
    }

    /**
     * Проверим, что NAN и INF превращаются в строки, а не ломают JSON.
     *
     * @see ContextNormalizer::normalize()
     */
    #[Test]
    public function nonFiniteFloatsAreStrings(): void
    {
        $normalizer = new ContextNormalizer();

        $json = $normalizer->encode($normalizer->normalize(['nan' => NAN, 'inf' => INF, 'ratio' => 1.0]));

        self::assertSame('{"nan":"NAN","inf":"INF","ratio":1.0}', $json);
    }

    /**
     * Проверим, что ресурсы, произвольные объекты и enum сводятся к скалярам.
     *
     * @see ContextNormalizer::normalize()
     */
    #[Test]
    public function resourcesObjectsAndEnumsAreScalars(): void
    {
        $normalizer = new ContextNormalizer();
        $resource   = fopen('php://memory', 'r');

        $normalized = $normalizer->normalize(['r' => $resource, 'o' => new stdClass(), 'e' => LogLevel::Error]);
        fclose($resource);

        self::assertSame(['r' => '[resource stream]', 'o' => '[object stdClass]', 'e' => LogLevel::Error->value], $normalized);
    }

    /**
     * Проверим, что исключение сериализуется вместе с цепочкой previous.
     *
     * @see ContextNormalizer::normalize()
     */
    #[Test]
    public function throwableIncludesPrevious(): void
    {
        $normalizer = new ContextNormalizer();

        $normalized = $normalizer->normalize(new RuntimeException('outer', 0, new LogicException('inner')));

        self::assertSame(RuntimeException::class, $normalized['class']);
        self::assertArrayHasKey('trace', $normalized);
        self::assertSame(LogicException::class, $normalized['previous']['class']);
        self::assertSame('inner', $normalized['previous']['message']);
    }

    /**
     * Проверим, что при сборе трассировок в отдельный список поле trace не выводится.
     *
     * @see ContextNormalizer::normalize()
     */
    #[Test]
    public function tracesAreCollectedSeparately(): void
    {
        $normalizer = new ContextNormalizer();
        $traces     = [];

        $normalized = $normalizer->normalize(['e' => new RuntimeException('boom')], $traces);

        self::assertArrayNotHasKey('trace', $normalized['e']);
        self::assertCount(1, $traces);
    }

    /**
     * Проверим, что слишком глубокая вложенность обрезается, а не приводит к ошибке кодирования.
     *
     * @see ContextNormalizer::normalize()
     */
    #[Test]
    public function deepNestingIsCut(): void
    {
        $normalizer = new ContextNormalizer(maxDepth: 2);

        $normalized = $normalizer->normalize(['a' => ['b' => ['c' => ['d' => 1]]]]);

        self::assertSame(['a' => ['b' => ['c' => '[max depth]']]], $normalized);
    }
}
