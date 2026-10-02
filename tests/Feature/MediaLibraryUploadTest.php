<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Tests\Feature;

use Asignua\FilamentImageMeta\Forms\ImageMetaPanel;
use Asignua\FilamentImageMeta\ImageMeta;
use Asignua\FilamentImageMeta\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Livewire\ArticleForm;
use Workbench\App\Models\Article;

class MediaLibraryUploadTest extends TestCase
{
    private const string COMPONENT = 'images_meta';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function form(array $options = [], ?Article $article = null, bool $multiple = false): Testable
    {
        return Livewire::test(ArticleForm::class, [
            'recordId' => $article?->getKey(),
            'options' => $options,
            'multiple' => $multiple,
        ]);
    }

    private function edit(Testable $form, string $key, array $data): Testable
    {
        return $form->callAction(
            TestAction::make(ImageMetaPanel::ACTION)->schemaComponent(self::COMPONENT, schema: 'form')->arguments(['key' => $key]),
            $data,
        );
    }

    private function firstKey(Testable $form): string
    {
        return (string) array_key_first($form->get('data.images'));
    }

    public function test_details_of_a_new_upload_become_custom_properties(): void
    {
        $form = $this->form(['locales' => ['en', 'uk'], 'caption' => true, 'title' => true, 'focalPoint' => true]);
        $form->set('data.images', [UploadedFile::fake()->image('a.jpg')]);

        $this->edit($form, $this->firstKey($form), [
            'alt' => ['en' => 'A red door', 'uk' => 'Червоні двері'],
            'caption' => ['en' => 'Front entrance', 'uk' => ''],
            'title' => ['en' => 'Door', 'uk' => ''],
            'focal' => ['x' => 33.333, 'y' => 66.6666],
        ])->assertHasNoActionErrors();

        $form->call('save')->assertHasNoErrors();

        $media = Media::query()->sole();

        $this->assertSame(['en' => 'A red door', 'uk' => 'Червоні двері'], $media->getCustomProperty('alt'));
        $this->assertSame(['en' => 'Front entrance'], $media->getCustomProperty('caption'));
        $this->assertSame(['en' => 'Door'], $media->getCustomProperty('title'));
        $this->assertEquals(['x' => 33.33, 'y' => 66.67], $media->getCustomProperty('focal_point'));
        $this->assertNull($media->getCustomProperty('alt_decorative'));
    }

    public function test_the_decorative_flag_uses_the_existing_alt_decorative_property(): void
    {
        $form = $this->form();
        $form->set('data.images', [UploadedFile::fake()->image('a.jpg')]);
        $this->edit($form, $this->firstKey($form), ['decorative' => true]);
        $form->call('save');

        $media = Media::query()->sole();

        $this->assertTrue($media->getCustomProperty('alt_decorative'));
        $this->assertNull($media->getCustomProperty('alt'));
        $this->assertTrue(ImageMeta::forMedia($media)->isDecorative());
    }

    public function test_the_property_names_come_from_config(): void
    {
        config()->set('image-meta.media_properties.alt', 'description');

        $form = $this->form();
        $form->set('data.images', [UploadedFile::fake()->image('a.jpg')]);
        $this->edit($form, $this->firstKey($form), ['alt' => 'A door']);
        $form->call('save');

        $media = Media::query()->sole();

        $this->assertSame('A door', $media->getCustomProperty('description'));
        $this->assertNull($media->getCustomProperty('alt'));
        $this->assertSame('A door', ImageMeta::forMedia($media)->alt());
    }

    public function test_existing_media_are_loaded_edited_and_saved(): void
    {
        $article = new Article;
        $article->forceFill(['title' => 'x'])->save();
        $media = $article->addMedia(UploadedFile::fake()->image('seed.jpg'))
            ->withCustomProperties(['alt' => 'Old alt'])
            ->toMediaCollection('images', 'public');

        $form = $this->form([], $article->refresh());
        $key = $this->firstKey($form);

        $this->assertSame($media->uuid, $key);

        $form->mountAction(
            TestAction::make(ImageMetaPanel::ACTION)->schemaComponent(self::COMPONENT, schema: 'form')->arguments(['key' => $key]),
        )->assertActionDataSet(['alt' => 'Old alt']);

        $form->unmountAction();

        $this->edit($form, $key, ['alt' => 'New alt']);
        $form->call('save');

        $this->assertSame('New alt', $media->refresh()->getCustomProperty('alt'));
    }

    public function test_clearing_a_detail_removes_the_property(): void
    {
        $article = new Article;
        $article->forceFill(['title' => 'x'])->save();
        $media = $article->addMedia(UploadedFile::fake()->image('seed.jpg'))
            ->withCustomProperties(['alt' => 'Old alt', 'caption' => 'Old caption'])
            ->toMediaCollection('images', 'public');

        $form = $this->form(['caption' => true], $article->refresh());
        $this->edit($form, $this->firstKey($form), ['alt' => '', 'caption' => 'Kept']);
        $form->call('save');

        $media->refresh();

        $this->assertNull($media->getCustomProperty('alt'));
        $this->assertSame('Kept', $media->getCustomProperty('caption'));
    }

    public function test_other_custom_properties_are_left_alone(): void
    {
        $article = new Article;
        $article->forceFill(['title' => 'x'])->save();
        $media = $article->addMedia(UploadedFile::fake()->image('seed.jpg'))
            ->withCustomProperties(['credit' => 'Photographer', 'alt' => 'Old'])
            ->toMediaCollection('images', 'public');

        $form = $this->form([], $article->refresh());
        $this->edit($form, $this->firstKey($form), ['alt' => 'New']);
        $form->call('save');

        $this->assertSame('Photographer', $media->refresh()->getCustomProperty('credit'));
    }

    public function test_every_file_of_a_gallery_gets_its_own_details(): void
    {
        $form = $this->form(multiple: true);
        $form->set('data.images', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')]);

        $keys = array_keys($form->get('data.images'));
        $this->edit($form, (string) $keys[0], ['alt' => 'First']);
        $this->edit($form, (string) $keys[1], ['alt' => 'Second']);
        $form->call('save');

        $alts = Media::query()->orderBy('id')->get()->map(fn (Media $media): mixed => $media->getCustomProperty('alt'))->all();

        $this->assertSame(['First', 'Second'], $alts);
    }

    public function test_the_panel_is_not_a_column_of_the_model(): void
    {
        $form = $this->form();
        $form->set('data.images', [UploadedFile::fake()->image('a.jpg')]);
        $this->edit($form, $this->firstKey($form), ['alt' => 'x']);

        $this->assertArrayNotHasKey('images_meta', $form->instance()->getSchema('form')->getState());
    }

    public function test_the_for_helper_reads_a_collection_by_name(): void
    {
        $article = new Article;
        $article->forceFill(['title' => 'x'])->save();
        $article->addMedia(UploadedFile::fake()->image('seed.jpg'))
            ->withCustomProperties(['alt' => 'From media'])
            ->toMediaCollection('images', 'public');

        $this->assertSame('From media', ImageMeta::for($article->refresh(), 'images')->alt());
    }
}
