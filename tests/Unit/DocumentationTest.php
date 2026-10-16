<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testReadmeUsesCurrentPublicApiWithoutObsoleteClaims(): void
    {
        $readme = file_get_contents(self::ROOT . '/README.md');
        self::assertIsString($readme);

        $requiredContent = [
            'DuplicateDetector::for(Contact::class)',
            '->scope(',
            "'max_candidates' => 1000",
            'searchComplete !== null',
            'docs/public-api.md',
            'SECURITY.md',
        ];
        foreach ($requiredContent as $required) {
            self::assertStringContainsString($required, $readme);
        }

        $obsoleteContent = [
            'Phase 2A is implemented',
            'No facade or automatic scan is registered',
            'No fuzzy matching',
        ];
        foreach ($obsoleteContent as $obsolete) {
            self::assertStringNotContainsString($obsolete, $readme);
        }
    }

    public function testReadmePhpExamplesParse(): void
    {
        $readme = file_get_contents(self::ROOT . '/README.md');
        self::assertIsString($readme);
        preg_match_all('/```php\R(.*?)```/s', $readme, $matches);
        self::assertNotEmpty($matches[1]);

        foreach ($matches[1] as $example) {
            $code = str_starts_with(ltrim($example), '<?php')
                ? $example
                : "<?php\n" . $example;
            $tokens = token_get_all($code, TOKEN_PARSE);
            self::assertNotEmpty($tokens);
        }

        self::addToAssertionCount(count($matches[1]));
    }

    #[DataProvider('documentLinks')]
    public function testReferencedDocumentsExist(string $path): void
    {
        self::assertFileExists(self::ROOT . '/' . $path);
    }

    /** @return iterable<string, array{string}> */
    public static function documentLinks(): iterable
    {
        yield 'public API' => ['docs/public-api.md'];
        yield 'configuration' => ['docs/configuration.md'];
        yield 'security' => ['SECURITY.md'];
        yield 'compatibility' => ['docs/release/compatibility-matrix.md'];
        yield 'release readiness' => ['docs/release/release-readiness.md'];
    }

    public function testSecurityPolicyDoesNotInventAReportingChannel(): void
    {
        $policy = file_get_contents(self::ROOT . '/SECURITY.md');
        self::assertIsString($policy);
        self::assertStringContainsString('no configured private vulnerability reporting channel', $policy);
        self::assertStringContainsString('must be selected and published by the owner', $policy);
        self::assertDoesNotMatchRegularExpression('/mailto:|security@/i', $policy);
    }
}
