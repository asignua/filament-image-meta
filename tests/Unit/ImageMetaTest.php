<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Tests\Unit;

use Asignua\FilamentImageMeta\ImageMeta;
use Asignua\FilamentImageMeta\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ImageMetaTest extends TestCase
{
    public function test_an_empty_value_is_missing_alt_not_decorative(): void
    {
        $meta = ImageMeta::fromArray(null);

        $this->assertTrue($meta->isEmpty());
        $this->assertTrue($meta->isMissingAlt());
        $this->assertFalse($meta->isDecorative());
        $this->assertSame('', $meta->altAttribute());
        $this->assertNull($meta->alt());
    }

    public function test_alt_is_per_locale_without_fallback_between_languages(): void
    {
        $meta = ImageMeta::fromArray(['alt' => ['en' => 'A door', 'uk' => '']]);

        $this->assertSame('A door', $meta->alt('en'));
        $this->assertNull($meta->alt('uk'));
        $this->assertNull($meta->alt('de'));
        $this->assertTrue($meta->isMissingAlt('uk'));
        $this->assertFalse($meta->isMissingAlt('en'));
    }

    public function test_the_locale_defaults_to_the_application_locale(): void
    {
        app()->setLocale('uk');

        $meta = ImageMeta::fromArray(['alt' => ['en' => 'A door', 'uk' => 'Двері']]);

        $this->assertSame('Двері', $meta->alt());
    }

    public function test_a_plain_string_applies_to_every_locale(): void
    {
        $meta = ImageMeta::fromArray(['alt' => 'A door']);

        $this->assertSame('A door', $meta->alt('en'));
        $this->assertSame('A door', $meta->alt('uk'));
    }

    public function test_a_configured_fallback_locale_is_used_only_when_asked_for(): void
    {
        $meta = ImageMeta::fromArray(['alt' => ['en' => 'A door']]);

        $this->assertNull($meta->alt('uk'));

        config()->set('image-meta.fallback_locale', 'en');

        $this->assertSame('A door', $meta->alt('uk'));
    }

    public function test_decorative_means_an_empty_alt_attribute_and_no_alt_text(): void
    {
        $meta = ImageMeta::fromArray(['decorative' => true, 'alt' => 'Leftover']);

        $this->assertTrue($meta->isDecorative());
        $this->assertNull($meta->alt());
        $this->assertSame('', $meta->altAttribute());
        $this->assertFalse($meta->isMissingAlt());
        $this->assertSame(['decorative' => true], $meta->toArray());
    }

    #[DataProvider('truthy')]
    public function test_decorative_accepts_the_usual_truthy_values(mixed $value): void
    {
        $this->assertTrue(ImageMeta::fromArray(['decorative' => $value])->isDecorative());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function truthy(): array
    {
        return ['true' => [true], 'one' => [1], 'string one' => ['1'], 'string true' => ['true']];
    }

    public function test_the_storable_shape_drops_empty_parts_and_round_trips(): void
    {
        $meta = ImageMeta::fromArray([
            'alt' => ['en' => ' A door ', 'uk' => ''],
            'caption' => '',
            'title' => ['en' => 'Door'],
            'focal' => ['x' => 10, 'y' => '20.5'],
        ]);

        $this->assertSame([
            'alt' => ['en' => 'A door'],
            'title' => ['en' => 'Door'],
            'focal' => ['x' => 10.0, 'y' => 20.5],
        ], $meta->toArray());

        $this->assertEquals($meta, ImageMeta::fromArray($meta->toArray()));
    }

    public function test_a_json_string_is_accepted(): void
    {
        $meta = ImageMeta::fromArray('{"alt":{"en":"A door"},"decorative":false}');

        $this->assertSame('A door', $meta->alt('en'));
    }

    public function test_garbage_does_not_throw(): void
    {
        $this->assertTrue(ImageMeta::fromArray('not json')->isEmpty());
        $this->assertTrue(ImageMeta::fromArray(42)->isEmpty());
        $this->assertTrue(ImageMeta::fromArray(['alt' => ['en' => ['nested']], 'focal' => 'x'])->isEmpty());
    }

    public function test_caption_and_title_are_per_locale(): void
    {
        $meta = ImageMeta::fromArray(['caption' => ['en' => 'Front'], 'title' => 'Door']);

        $this->assertSame('Front', $meta->caption('en'));
        $this->assertNull($meta->caption('uk'));
        $this->assertSame('Door', $meta->title('uk'));
    }

    /**
     * @param array{x: float, y: float}|null $expected
     */
    #[DataProvider('focalPoints')]
    public function test_the_focal_point_is_bounded(mixed $raw, ?array $expected): void
    {
        $this->assertSame($expected, ImageMeta::focal($raw));
    }

    /**
     * @return array<string, array{mixed, array{x: float, y: float}|null}>
     */
    public static function focalPoints(): array
    {
        return [
            'inside' => [['x' => 25, 'y' => 75], ['x' => 25.0, 'y' => 75.0]],
            'edges' => [['x' => 0, 'y' => 100], ['x' => 0.0, 'y' => 100.0]],
            'above' => [['x' => 101, 'y' => 1000], ['x' => 100.0, 'y' => 100.0]],
            'below' => [['x' => -1, 'y' => -0.5], ['x' => 0.0, 'y' => 0.0]],
            'rounded' => [['x' => 33.33333, 'y' => 66.66666], ['x' => 33.33, 'y' => 66.67]],
            'numeric strings' => [['x' => '12.5', 'y' => '50'], ['x' => 12.5, 'y' => 50.0]],
            'one axis' => [['x' => 10], null],
            'not numbers' => [['x' => 'a', 'y' => 'b'], null],
            'infinite' => [['x' => INF, 'y' => 1], null],
            'not an array' => ['50,50', null],
            'null' => [null, null],
        ];
    }

    public function test_object_position(): void
    {
        $this->assertSame('35% 20.5%', ImageMeta::fromArray(['focal' => ['x' => 35, 'y' => 20.5]])->objectPosition());
        $this->assertSame('0% 100%', ImageMeta::fromArray(['focal' => ['x' => 0, 'y' => 100]])->objectPosition());
        $this->assertNull(ImageMeta::empty()->objectPosition());
    }
}
