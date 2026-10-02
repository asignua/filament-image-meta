<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Tests\Feature;

use Asignua\FilamentImageMeta\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TranslationsTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        $locales = [];

        foreach (glob(dirname(__DIR__, 2).'/resources/lang/*/image-meta.php') ?: [] as $file) {
            $locale = basename(dirname($file));
            $locales[$locale] = [$locale];
        }

        return $locales;
    }

    public function test_all_ten_languages_ship(): void
    {
        $this->assertSame(
            ['de', 'en', 'es', 'fr', 'it', 'nl', 'pl', 'pt_BR', 'tr', 'uk'],
            array_keys(self::locales()),
        );
    }

    #[DataProvider('locales')]
    public function test_every_locale_has_the_english_keys_and_placeholders(string $locale): void
    {
        $english = $this->strings('en');
        $strings = $this->strings($locale);

        $this->assertSame([], array_diff(array_keys($english), array_keys($strings)), "Keys missing in {$locale}");
        $this->assertSame([], array_diff(array_keys($strings), array_keys($english)), "Extra keys in {$locale}");

        foreach ($english as $key => $text) {
            $this->assertSame(
                $this->placeholders($text),
                $this->placeholders($strings[$key]),
                "Placeholders of {$locale}.{$key} differ from English",
            );
            $this->assertNotSame('', trim($strings[$key]), "{$locale}.{$key} is empty");
        }
    }

    /**
     * @return array<string, string>
     */
    private function strings(string $locale): array
    {
        /** @var array<string, string> */
        return require dirname(__DIR__, 2)."/resources/lang/{$locale}/image-meta.php";
    }

    /**
     * @return array<int, string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+/', $text, $matches);

        $found = array_unique($matches[0]);
        sort($found);

        return $found;
    }

    public function test_the_namespace_is_registered(): void
    {
        $this->assertSame('Alt text', __('image-meta::image-meta.alt'));
    }
}
