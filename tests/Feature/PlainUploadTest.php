<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Tests\Feature;

use Asignua\FilamentImageMeta\Forms\ImageMetaPanel;
use Asignua\FilamentImageMeta\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Livewire\PostForm;
use Workbench\App\Models\Post;

class PlainUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function form(array $options = [], ?Post $post = null, bool $multiple = false, ?string $uploadMode = null): Testable
    {
        return Livewire::test(PostForm::class, [
            'recordId' => $post?->getKey(),
            'options' => $options,
            'multiple' => $multiple,
            'uploadMode' => $uploadMode,
        ]);
    }

    private function storedPost(string $alt = 'Stored alt'): Post
    {
        Storage::disk('public')->put('posts/seed.jpg', (string) UploadedFile::fake()->image('seed.jpg')->getContent());

        $post = new Post;
        $post->forceFill(['photo' => 'posts/seed.jpg', 'photo_meta' => ['posts/seed.jpg' => ['alt' => $alt]]])->save();

        return $post;
    }

    private function upload(Testable $form, string $name = 'a.jpg'): string
    {
        $form->set('data.photo', [UploadedFile::fake()->image($name)]);

        /** @var array<string, mixed> $state */
        $state = $form->get('data.photo');

        return (string) array_key_first($state);
    }

    private function edit(Testable $form, string $key, array $data, string $name = 'photo_meta'): Testable
    {
        return $form->callAction(
            TestAction::make(ImageMetaPanel::ACTION)->schemaComponent($name, schema: 'form')->arguments(['key' => $key]),
            $data,
        );
    }

    public function test_the_panel_state_is_a_sibling_of_the_upload(): void
    {
        $form = $this->form();

        $this->assertEqualsCanonicalizing(['photo', 'photo_meta'], array_keys($form->get('data')));
    }

    public function test_details_of_a_new_upload_are_saved_under_the_stored_path(): void
    {
        $form = $this->form(['locales' => ['en', 'uk'], 'caption' => true]);
        $key = $this->upload($form);

        $this->edit($form, $key, [
            'decorative' => false,
            'alt' => ['en' => 'A red door', 'uk' => 'Червоні двері'],
            'caption' => ['en' => 'Front entrance', 'uk' => ''],
        ])->assertHasNoActionErrors();

        $form->call('save')->assertHasNoErrors();

        $post = Post::query()->sole();

        $this->assertNotEmpty($post->photo);
        Storage::disk('public')->assertExists((string) $post->photo);
        $this->assertSame([
            (string) $post->photo => [
                'alt' => ['en' => 'A red door', 'uk' => 'Червоні двері'],
                'caption' => ['en' => 'Front entrance'],
            ],
        ], $post->photo_meta);
    }

    public function test_a_language_less_field_stores_plain_strings(): void
    {
        $form = $this->form(['title' => true]);
        $key = $this->upload($form);

        $this->edit($form, $key, ['alt' => 'A red door', 'title' => 'Door'])->assertHasNoActionErrors();
        $form->call('save');

        $this->assertSame(['alt' => 'A red door', 'title' => 'Door'], Post::query()->sole()->photo_meta[(string) Post::query()->sole()->photo]);
    }

    public function test_decorative_clears_the_alt_text(): void
    {
        $form = $this->form(['locales' => ['en']]);
        $key = $this->upload($form);

        $this->edit($form, $key, ['decorative' => true, 'alt' => ['en' => 'ignored']])->assertHasNoActionErrors();
        $form->call('save');

        $post = Post::query()->sole();

        $this->assertSame([(string) $post->photo => ['decorative' => true]], $post->photo_meta);
    }

    public function test_options_that_are_off_drop_the_submitted_value(): void
    {
        $form = $this->form(['alt' => true, 'caption' => false, 'focalPoint' => false]);
        $key = $this->upload($form);

        $this->edit($form, $key, ['alt' => 'x', 'caption' => 'hidden', 'focal' => ['x' => 1, 'y' => 2]]);
        $form->call('save');

        $post = Post::query()->sole();

        $this->assertSame(['alt' => 'x'], $post->photo_meta[(string) $post->photo]);
    }

    public function test_the_focal_point_is_clamped_into_the_image(): void
    {
        $form = $this->form(['focalPoint' => true]);
        $key = $this->upload($form);

        $this->edit($form, $key, ['focal' => ['x' => 150, 'y' => -20]])->assertHasNoActionErrors();
        $form->call('save');

        $post = Post::query()->sole();

        $this->assertEquals(['focal' => ['x' => 100, 'y' => 0]], $post->photo_meta[(string) $post->photo]);
    }

    public function test_details_survive_a_reload_and_can_be_edited_again(): void
    {
        $form = $this->form(['locales' => ['en', 'uk']]);
        $key = $this->upload($form);
        $this->edit($form, $key, ['alt' => ['en' => 'First', 'uk' => '']]);
        $form->call('save');

        $post = Post::query()->sole();
        $reloaded = $this->form(['locales' => ['en', 'uk']], $post);

        $storedKey = (string) array_key_first($reloaded->get('data.photo'));

        $this->edit($reloaded, $storedKey, ['alt' => ['en' => 'Second', 'uk' => 'Другий']])->assertHasNoActionErrors();
        $reloaded->call('save');

        $this->assertSame(
            ['alt' => ['en' => 'Second', 'uk' => 'Другий']],
            $post->refresh()->photo_meta[(string) $post->photo],
        );
    }

    public function test_the_modal_is_prefilled_with_the_stored_details(): void
    {
        $form = $this->form(['locales' => ['en', 'uk']]);
        $key = $this->upload($form);
        $this->edit($form, $key, ['alt' => ['en' => 'Prefilled', 'uk' => 'Заповнено']]);
        $form->call('save');

        $reloaded = $this->form(['locales' => ['en', 'uk']], Post::query()->sole());
        $storedKey = (string) array_key_first($reloaded->get('data.photo'));

        $reloaded->mountAction(
            TestAction::make(ImageMetaPanel::ACTION)->schemaComponent('photo_meta', schema: 'form')->arguments(['key' => $storedKey]),
        )->assertActionDataSet(['alt' => ['en' => 'Prefilled', 'uk' => 'Заповнено'], 'decorative' => false]);
    }

    public function test_a_removed_file_takes_its_details_with_it(): void
    {
        $form = $this->form();
        $key = $this->upload($form);
        $this->edit($form, $key, ['alt' => 'Gone soon']);
        $form->call('save');

        $reloaded = $this->form([], Post::query()->sole());
        $reloaded->set('data.photo', []);
        $reloaded->call('save');

        $this->assertNull(Post::query()->sole()->photo_meta);
    }

    public function test_a_gallery_keeps_details_per_file(): void
    {
        $form = $this->form(multiple: true);
        $form->set('data.gallery', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')]);

        $keys = array_keys($form->get('data.gallery'));

        $this->assertCount(2, $keys);

        $this->edit($form, (string) $keys[0], ['alt' => 'First image'], 'gallery_meta');
        $this->edit($form, (string) $keys[1], ['alt' => 'Second image'], 'gallery_meta');
        $form->call('save');

        $post = Post::query()->sole();

        $this->assertCount(2, $post->gallery);
        $this->assertSame('First image', $post->gallery_meta[$post->gallery[0]]['alt']);
        $this->assertSame('Second image', $post->gallery_meta[$post->gallery[1]]['alt']);
    }

    public function test_a_forged_key_is_rejected(): void
    {
        $form = $this->form();
        $this->upload($form);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->edit($form, 'not-a-file', ['alt' => 'x']);
    }

    public function test_details_can_be_edited_again_after_a_save_in_the_same_session(): void
    {
        $form = $this->form(['locales' => ['en']]);
        $key = $this->upload($form);

        $this->edit($form, $key, ['alt' => ['en' => 'First']])->assertHasNoActionErrors();
        $form->call('save')->assertHasNoErrors();

        // The same component, no reload: the modal must show what was saved, and a new edit must win.
        $form->mountAction(
            TestAction::make(ImageMetaPanel::ACTION)->schemaComponent('photo_meta', schema: 'form')->arguments(['key' => $key]),
        )->assertActionDataSet(['alt' => ['en' => 'First']]);
        $form->unmountAction();

        $this->edit($form, $key, ['alt' => ['en' => 'Second']])->assertHasNoActionErrors();
        $form->call('save')->assertHasNoErrors();

        $post = Post::query()->sole();

        $this->assertSame([(string) $post->photo => ['alt' => ['en' => 'Second']]], $post->photo_meta);
        $this->assertCount(1, $form->get('data.photo_meta'));

        $this->edit($form, $key, ['alt' => ['en' => '']])->assertHasNoActionErrors();
        $form->call('save')->assertHasNoErrors();

        $this->assertNull($post->refresh()->photo_meta);
    }

    public function test_a_disabled_upload_locks_its_details(): void
    {
        $post = $this->storedPost();
        $form = $this->form([], $post, uploadMode: 'disabled');
        $key = (string) array_key_first($form->get('data.photo'));

        $form->assertActionHidden(
            TestAction::make(ImageMetaPanel::ACTION)->schemaComponent('photo_meta', schema: 'form')->arguments(['key' => $key]),
        );

        // A forged write to the panel's public state is not saved either.
        $form->set('data.photo_meta', [ImageMetaPanel::slotForIdentifier('posts/seed.jpg') => ['alt' => 'Forged']]);
        $form->call('save');

        $this->assertSame(['posts/seed.jpg' => ['alt' => 'Stored alt']], $post->refresh()->photo_meta);
    }

    public function test_a_hidden_upload_hides_its_details(): void
    {
        $post = $this->storedPost('Secret alt');
        $form = $this->form([], $post, uploadMode: 'hidden');

        // Nothing of the panel is rendered; its action is not even resolvable (a hidden component).
        $form->assertDontSee('Secret alt')->assertDontSee('seed.jpg');

        $form->set('data.photo_meta', [ImageMetaPanel::slotForIdentifier('posts/seed.jpg') => ['alt' => 'Forged']]);
        $form->call('save');

        $this->assertSame(['posts/seed.jpg' => ['alt' => 'Secret alt']], $post->refresh()->photo_meta);
    }

    public function test_texts_written_straight_into_the_state_are_cut_to_the_limits(): void
    {
        config()->set('image-meta.alt_max_length', 10);

        $form = $this->form(['caption' => true]);
        $key = $this->upload($form);

        $form->set('data.photo_meta', [ImageMetaPanel::slotForKey($key) => [
            'alt' => str_repeat('a', 50),
            'caption' => str_repeat('c', 1000),
        ]]);
        $form->call('save');

        $post = Post::query()->sole();
        $meta = $post->photo_meta[(string) $post->photo];

        $this->assertSame(str_repeat('a', 10), $meta['alt']);
        $this->assertSame(255, mb_strlen($meta['caption']));
    }

    public function test_a_file_re_uploaded_under_the_same_path_does_not_inherit_the_old_details(): void
    {
        $post = $this->storedPost('Old');
        $form = $this->form([], $post, uploadMode: 'preserve');

        $form->set('data.photo', []);
        $key = $this->upload($form, 'seed.jpg');
        $this->edit($form, $key, ['alt' => 'New'])->assertHasNoActionErrors();
        $form->call('save')->assertHasNoErrors();

        $this->assertSame('posts/seed.jpg', $post->refresh()->photo);
        $this->assertSame(['posts/seed.jpg' => ['alt' => 'New']], $post->photo_meta);
        $this->assertSame([ImageMetaPanel::slotForIdentifier('posts/seed.jpg') => ['alt' => 'New']], $form->get('data.photo_meta'));
    }

    public function test_a_file_re_uploaded_under_the_same_path_without_details_has_none(): void
    {
        $post = $this->storedPost('Old');
        $form = $this->form([], $post, uploadMode: 'preserve');

        $form->set('data.photo', []);
        $this->upload($form, 'seed.jpg');
        $form->call('save')->assertHasNoErrors();

        $this->assertSame('posts/seed.jpg', $post->refresh()->photo);
        $this->assertNull($post->photo_meta);
    }

    public function test_the_details_of_an_upload_win_over_a_slot_left_under_the_same_path(): void
    {
        $post = $this->storedPost('Old');
        $form = $this->form([], $post);

        // The upload's state already holds the stored path under a new item key (as after a
        // save with a deterministic file name); the details typed for that item must win.
        $form->set('data.photo_meta', [
            ImageMetaPanel::slotForIdentifier('posts/seed.jpg') => ['alt' => 'Old'],
            ImageMetaPanel::slotForKey('k2') => ['alt' => 'New'],
        ]);
        $form->set('data.photo', ['k2' => 'posts/seed.jpg']);
        $form->call('save')->assertHasNoErrors();

        $this->assertSame(['posts/seed.jpg' => ['alt' => 'New']], $post->refresh()->photo_meta);
    }

    public function test_stored_texts_longer_than_the_limit_are_kept_on_save(): void
    {
        $long = str_repeat('a', 50);
        $post = $this->storedPost($long);

        config()->set('image-meta.alt_max_length', 10);

        $form = $this->form([], $post);
        $form->call('save')->assertHasNoErrors();

        $this->assertSame(['posts/seed.jpg' => ['alt' => $long]], $post->refresh()->photo_meta);
    }
}
