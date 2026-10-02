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
use Workbench\App\Livewire\ArticleForm;
use Workbench\App\Livewire\PostForm;
use Workbench\App\Models\Article;
use Workbench\App\Models\Post;

class RequireAltTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function plain(array $options): Testable
    {
        $form = Livewire::test(PostForm::class, ['options' => $options]);
        $form->set('data.photo', [UploadedFile::fake()->image('a.jpg')]);

        return $form;
    }

    private function describe(Testable $form, string $component, array $data): void
    {
        $key = (string) array_key_first($form->get('data.photo'));

        $form->callAction(
            TestAction::make(ImageMetaPanel::ACTION)->schemaComponent($component, schema: 'form')->arguments(['key' => $key]),
            $data,
        );
    }

    public function test_an_undescribed_image_blocks_the_save(): void
    {
        $form = $this->plain(['requireAlt' => true]);

        $form->call('save')->assertHasErrors(['data.photo']);

        $this->assertSame(0, Post::query()->count());
    }

    public function test_the_error_names_the_file(): void
    {
        $form = $this->plain(['requireAlt' => true]);

        $form->call('save')->assertSee(__('image-meta::image-meta.alt_required', ['file' => 'a.jpg']));
    }

    public function test_alt_text_satisfies_the_rule(): void
    {
        $form = $this->plain(['requireAlt' => true]);
        $this->describe($form, 'photo_meta', ['alt' => 'A door']);

        $form->call('save')->assertHasNoErrors();

        $this->assertSame(1, Post::query()->count());
    }

    public function test_marking_it_decorative_satisfies_the_rule(): void
    {
        $form = $this->plain(['requireAlt' => true]);
        $this->describe($form, 'photo_meta', ['decorative' => true]);

        $form->call('save')->assertHasNoErrors();
    }

    public function test_without_the_option_nothing_is_required(): void
    {
        $form = $this->plain([]);

        $form->call('save')->assertHasNoErrors();
    }

    public function test_only_the_first_locale_is_required_by_default(): void
    {
        $form = $this->plain(['requireAlt' => true, 'locales' => ['en', 'uk']]);
        $this->describe($form, 'photo_meta', ['alt' => ['en' => 'A door', 'uk' => '']]);

        $form->call('save')->assertHasNoErrors();
    }

    public function test_a_missing_first_locale_blocks_the_save(): void
    {
        $form = $this->plain(['requireAlt' => true, 'locales' => ['en', 'uk']]);
        $this->describe($form, 'photo_meta', ['alt' => ['en' => '', 'uk' => 'Двері']]);

        $form->call('save')->assertHasErrors(['data.photo']);
    }

    public function test_the_required_locales_can_be_listed(): void
    {
        $form = $this->plain(['requireAlt' => true, 'locales' => ['en', 'uk'], 'requiredLocales' => ['en', 'uk']]);
        $this->describe($form, 'photo_meta', ['alt' => ['en' => 'A door', 'uk' => '']]);

        $form->call('save')->assertHasErrors(['data.photo']);
    }

    public function test_the_rule_can_be_a_closure(): void
    {
        $form = $this->plain(['requireAlt' => 'closure-false']);

        $form->call('save')->assertHasNoErrors();
    }

    public function test_the_rule_also_guards_a_media_library_upload(): void
    {
        $form = Livewire::test(ArticleForm::class, ['options' => ['requireAlt' => true]]);
        $form->set('data.images', [UploadedFile::fake()->image('a.jpg')]);

        $form->call('save')->assertHasErrors(['data.images']);

        $this->assertSame(0, Article::query()->count());
    }
}
