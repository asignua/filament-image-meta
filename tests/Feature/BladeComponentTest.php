<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Tests\Feature;

use Asignua\FilamentImageMeta\ImageMeta;
use Asignua\FilamentImageMeta\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

class BladeComponentTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     */
    private function html(string $template, array $data = []): string
    {
        return trim(Blade::render($template, $data));
    }

    public function test_alt_text_is_rendered_and_escaped(): void
    {
        $html = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" locale="en" />', [
            'meta' => ImageMeta::fromArray(['alt' => ['en' => 'A "red" <door>']]),
        ]);

        $this->assertStringContainsString('src="/a.jpg"', $html);
        $this->assertStringContainsString('alt="A &quot;red&quot; &lt;door&gt;"', $html);
        $this->assertStringNotContainsString('role=', $html);
        $this->assertStringNotContainsString('aria-hidden', $html);
    }

    public function test_a_decorative_image_gets_an_empty_alt_and_no_aria_misuse(): void
    {
        $html = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" />', [
            'meta' => ImageMeta::fromArray(['decorative' => true, 'title' => 'Ignored']),
        ]);

        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringNotContainsString('aria-hidden', $html);
        $this->assertStringNotContainsString('role=', $html);
        $this->assertStringNotContainsString('title=', $html);
    }

    public function test_a_missing_alt_is_an_empty_alt_never_the_file_name(): void
    {
        $html = $this->html('<x-image-meta::img src="/uploads/IMG_0042-final.jpg" :meta="$meta" />', [
            'meta' => ImageMeta::empty(),
        ]);

        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringNotContainsString('alt="IMG', $html);
        $this->assertStringNotContainsString('data-alt-missing', $html);
    }

    public function test_the_missing_alt_marker_is_opt_in(): void
    {
        config()->set('image-meta.mark_missing_alt', true);

        $missing = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" />', ['meta' => ImageMeta::empty()]);
        $decorative = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" />', ['meta' => ImageMeta::fromArray(['decorative' => true])]);
        $described = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" />', ['meta' => ImageMeta::fromArray(['alt' => 'x'])]);

        $this->assertStringContainsString('data-alt-missing', $missing);
        $this->assertStringNotContainsString('data-alt-missing', $decorative);
        $this->assertStringNotContainsString('data-alt-missing', $described);
    }

    public function test_the_focal_point_becomes_object_position(): void
    {
        $html = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" class="object-cover" />', [
            'meta' => ImageMeta::fromArray(['alt' => 'x', 'focal' => ['x' => 30, 'y' => 70]]),
        ]);

        $this->assertStringContainsString('style="object-position: 30% 70%"', $html);
        $this->assertStringContainsString('class="object-cover"', $html);
    }

    public function test_without_a_focal_point_there_is_no_style(): void
    {
        $html = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" />', ['meta' => ImageMeta::fromArray(['alt' => 'x'])]);

        $this->assertStringNotContainsString('style=', $html);
    }

    public function test_the_title_attribute_follows_the_locale(): void
    {
        $html = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" locale="uk" />', [
            'meta' => ImageMeta::fromArray(['alt' => 'x', 'title' => ['en' => 'Door', 'uk' => 'Двері']]),
        ]);

        $this->assertStringContainsString('title="Двері"', $html);
    }

    public function test_the_stored_array_can_be_passed_directly(): void
    {
        $html = $this->html('<x-image-meta::img src="/a.jpg" :meta="$meta" />', ['meta' => ['alt' => 'From array']]);

        $this->assertStringContainsString('alt="From array"', $html);
    }

    public function test_a_null_meta_is_a_missing_alt(): void
    {
        $html = $this->html('<x-image-meta::img src="/a.jpg" />');

        $this->assertStringContainsString('alt=""', $html);
    }

    public function test_the_figure_renders_the_caption_of_the_locale(): void
    {
        $html = $this->html('<x-image-meta::figure src="/a.jpg" :meta="$meta" locale="uk" class="wide" img-class="object-cover" />', [
            'meta' => ImageMeta::fromArray(['alt' => ['uk' => 'Двері'], 'caption' => ['en' => 'Front', 'uk' => 'Вхід']]),
        ]);

        $this->assertStringContainsString('<figure class="wide">', $html);
        $this->assertStringContainsString('<figcaption>Вхід</figcaption>', $html);
        $this->assertStringContainsString('alt="Двері"', $html);
        $this->assertStringContainsString('class="object-cover"', $html);
    }

    public function test_the_figure_has_no_caption_element_without_a_caption(): void
    {
        $html = $this->html('<x-image-meta::figure src="/a.jpg" :meta="$meta" />', ['meta' => ImageMeta::fromArray(['alt' => 'x'])]);

        $this->assertStringNotContainsString('figcaption', $html);
    }
}
