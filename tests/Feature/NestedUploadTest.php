<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Tests\Feature;

use Asignua\FilamentImageMeta\Forms\ImageMetaPanel;
use Asignua\FilamentImageMeta\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Workbench\App\Livewire\BlocksForm;
use Workbench\App\Models\Post;

/**
 * An upload inside a Repeater item without a relationship: the record has no `photo_meta` column,
 * the details live in the item's own JSON.
 */
class NestedUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_save_keeps_what_the_field_does_not_collect_and_does_not_cut_stored_texts(): void
    {
        Storage::disk('public')->put('posts/seed.jpg', (string) UploadedFile::fake()->image('seed.jpg')->getContent());

        $long = str_repeat('a', 300);
        $post = new Post;
        $post->forceFill(['blocks' => ['item0' => [
            'photo' => 'posts/seed.jpg',
            'photo_meta' => ['posts/seed.jpg' => ['alt' => ['en' => $long, 'uk' => 'Двері'], 'caption' => 'Cap']],
        ]]])->save();

        Livewire::test(BlocksForm::class, ['recordId' => $post->getKey(), 'options' => ['locales' => ['en']]])
            ->call('save')
            ->assertHasNoErrors();

        $meta = array_values($post->refresh()->blocks)[0]['photo_meta']['posts/seed.jpg'];

        $this->assertSame($long, $meta['alt']['en']);
        $this->assertSame('Двері', $meta['alt']['uk']);
        $this->assertSame('Cap', $meta['caption']);
    }

    public function test_a_forged_snapshot_in_a_repeater_item_does_not_lift_the_limit_or_inject_texts(): void
    {
        Storage::disk('public')->put('posts/seed.jpg', (string) UploadedFile::fake()->image('seed.jpg')->getContent());
        config()->set('image-meta.alt_max_length', 10);

        $post = new Post;
        $post->forceFill(['blocks' => ['item0' => [
            'photo' => 'posts/seed.jpg',
            'photo_meta' => ['posts/seed.jpg' => ['alt' => 'Short']],
        ]]])->save();

        $long = str_repeat('b', 50);
        $slot = ImageMetaPanel::slotForIdentifier('posts/seed.jpg');
        $forged = [$slot => ['alt' => ['en' => $long], 'caption' => 'Forged']];

        $t = Livewire::test(BlocksForm::class, ['recordId' => $post->getKey(), 'options' => ['locales' => ['en']]]);

        // Filament re-keys the items with UUIDs on hydration: target the real item, not a new one.
        $key = array_key_first($t->get('data.blocks'));
        $this->assertNotSame('item0', $key);

        $t->set('data.blocks.'.$key.'.photo_meta.'.ImageMetaPanel::STORED, ['json' => json_encode($forged), 'sig' => 'forged'])
            ->set('data.blocks.'.$key.'.photo_meta.'.$slot, ['alt' => ['en' => $long]])
            ->call('save');

        $blocks = $post->refresh()->blocks;
        $this->assertCount(1, $blocks);

        $meta = array_values($blocks)[0]['photo_meta']['posts/seed.jpg'] ?? [];
        $alt = $meta['alt'] ?? '';

        $this->assertLessThanOrEqual(10, mb_strlen(is_array($alt) ? (string) reset($alt) : $alt));
        $this->assertArrayNotHasKey('caption', $meta);
    }
}
