<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Tests\Feature;

use Asignua\FilamentImageMeta\ImageMeta;
use Asignua\FilamentImageMeta\Tests\TestCase;
use Workbench\App\Models\Post;

class ModelHelperTest extends TestCase
{
    private function makePost(): Post
    {
        $post = new Post;
        $post->forceFill([
            'photo' => 'posts/a.jpg',
            'photo_meta' => ['posts/a.jpg' => ['alt' => ['en' => 'A door'], 'focal' => ['x' => 10, 'y' => 20]]],
            'gallery' => ['posts/one.jpg', 'posts/two.jpg'],
            'gallery_meta' => ['posts/two.jpg' => ['decorative' => true]],
        ])->save();

        return $post->refresh();
    }

    public function test_for_reads_the_sibling_column(): void
    {
        $meta = ImageMeta::for($this->makePost(), 'photo');

        $this->assertSame('A door', $meta->alt('en'));
        $this->assertSame('10% 20%', $meta->objectPosition());
    }

    public function test_for_picks_a_file_of_a_gallery_by_path(): void
    {
        $post = $this->makePost();

        $this->assertTrue(ImageMeta::for($post, 'gallery', 'posts/two.jpg')->isDecorative());
        $this->assertTrue(ImageMeta::for($post, 'gallery', 'posts/one.jpg')->isMissingAlt());
    }

    public function test_for_without_a_path_takes_the_first_file(): void
    {
        $this->assertTrue(ImageMeta::for($this->makePost(), 'gallery')->isMissingAlt());
    }

    public function test_all_for_is_keyed_by_path(): void
    {
        $all = ImageMeta::allFor($this->makePost(), 'gallery');

        $this->assertSame(['posts/two.jpg'], array_keys($all));
    }

    public function test_a_model_without_details_gives_an_empty_meta(): void
    {
        $post = new Post;
        $post->forceFill(['photo' => 'posts/x.jpg'])->save();

        $this->assertTrue(ImageMeta::for($post->refresh(), 'photo')->isEmpty());
    }

    public function test_the_meta_suffix_can_be_changed(): void
    {
        config()->set('image-meta.meta_suffix', '_details');

        $this->assertSame([], ImageMeta::allFor($this->makePost(), 'photo'));
        $this->assertCount(1, ImageMeta::allFor($this->makePost(), 'photo', 'photo_meta'));
    }
}
