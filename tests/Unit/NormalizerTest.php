<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use InvalidArgumentException;
use Lzk\DuplicateDetector\Contracts\NormalizerInterface;
use Lzk\DuplicateDetector\Normalizers\EmailNormalizer;
use Lzk\DuplicateDetector\Normalizers\PhoneNormalizer;
use Lzk\DuplicateDetector\Normalizers\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NormalizerTest extends TestCase
{
    #[DataProvider('values')]
    public function testDeterministicNormalization(
        NormalizerInterface $normalizer,
        ?string $input,
        ?string $expected,
    ): void {
        $original = $input;
        self::assertSame($expected, $normalizer->normalize($input));
        self::assertSame($expected, $normalizer->normalize($normalizer->normalize($input)));
        self::assertSame($original, $input);
    }

    /** @return iterable<string, array{NormalizerInterface, ?string, ?string}> */
    public static function values(): iterable
    {
        yield 'unicode email' => [new EmailNormalizer(), " \tÉXAMPLE@EXAMPLE.COM\n", 'éxample@example.com'];
        yield 'email preserves internal spaces' => [new EmailNormalizer(), 'A B@X.COM', 'a b@x.com'];
        yield 'text unicode spaces' => [new TextNormalizer(), "  ÁNA\t  MARÍA\u{00A0}\n PÉREZ ", 'ána maría pérez'];
        yield 'international phone' => [new PhoneNormalizer(), ' +54 (11) 1234-5678 ', '+541112345678'];
        yield 'phone dots and slash' => [new PhoneNormalizer(), '011.1234/5678', '01112345678'];
        yield 'phone leading zero' => [new PhoneNormalizer(), '0011-234', '0011234'];
        yield 'phone zero' => [new PhoneNormalizer(), '0', '0'];
        yield 'phone separators only' => [new PhoneNormalizer(), ' () - . ', null];
        foreach ([new EmailNormalizer(), new TextNormalizer(), new PhoneNormalizer()] as $i => $normalizer) {
            yield 'null ' . $i => [$normalizer, null, null];
            yield 'empty ' . $i => [$normalizer, '', null];
            yield 'whitespace ' . $i => [$normalizer, " \t\u{00A0}", null];
        }
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidValuesAreRejectedWithoutEchoingThem(NormalizerInterface $normalizer, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $normalizer->normalize($value);
    }

    /** @return iterable<string, array{NormalizerInterface, string|int|float|bool}> */
    public static function invalidInputs(): iterable
    {
        yield 'phone extension' => [new PhoneNormalizer(), '123 ext 45'];
        yield 'phone letters' => [new PhoneNormalizer(), 'private-value'];
        yield 'phone plus only' => [new PhoneNormalizer(), '+'];
        yield 'phone two pluses' => [new PhoneNormalizer(), '++123'];
        yield 'invalid utf8' => [new EmailNormalizer(), "\xFF"];
        yield 'int phone loses zeros' => [new PhoneNormalizer(), 123];
        yield 'bool text' => [new TextNormalizer(), true];
        yield 'float email' => [new EmailNormalizer(), 1.5];
    }

    public function testUnknownOptionsFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EmailNormalizer())->normalize('a@example.com', ['unknown' => true]);
    }

    public function testNoCountryOrProviderRulesAreAssumed(): void
    {
        $phone = new PhoneNormalizer();
        self::assertNotSame($phone->normalize('+5411'), $phone->normalize('005411'));
        $email = new EmailNormalizer();
        self::assertNotSame($email->normalize('a+b@x.com'), $email->normalize('a@x.com'));
        self::assertNotSame($email->normalize('a.b@x.com'), $email->normalize('ab@x.com'));
    }
}
