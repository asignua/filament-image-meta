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
    private function form(array $options = [], ?Post $post = null, bool $multiple = false): Testable
    {
        return Livewire::test(PostForm::class, [
            'recordId' => $post?->getKey(),
            'options' => $options,
            'multiple' => $multiple,
        ]);
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
}
