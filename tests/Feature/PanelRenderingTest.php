<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Tests\Feature;

use Asignua\FilamentImageMeta\Forms\FocalPointPicker;
use Asignua\FilamentImageMeta\Forms\ImageMetaPanel;
use Asignua\FilamentImageMeta\ImageMetaServiceProvider;
use Asignua\FilamentImageMeta\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Livewire\PostForm;
use Workbench\App\Models\Post;

class PanelRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function form(array $options = [], ?Post $post = null): Testable
    {
        return Livewire::test(PostForm::class, ['recordId' => $post?->getKey(), 'options' => $options]);
    }

    private function action(string $key): TestAction
    {
        return TestAction::make(ImageMetaPanel::ACTION)->schemaComponent('photo_meta', schema: 'form')->arguments(['key' => $key]);
    }

    private function withUpload(Testable $form): string
    {
        $form->set('data.photo', [UploadedFile::fake()->image('door.jpg')]);

        return (string) array_key_first($form->get('data.photo'));
    }

    public function test_nothing_is_rendered_without_files(): void
    {
        $this->form()->assertDontSee(__('image-meta::image-meta.edit_details'));
    }

    public function test_a_file_shows_its_name_the_missing_alt_badge_and_the_button(): void
    {
        $form = $this->form();
        $this->withUpload($form);

        $form->assertSee('door.jpg')
            ->assertSee(__('image-meta::image-meta.status_missing'))
            ->assertSee(__('image-meta::image-meta.edit_details'));
    }

    public function test_the_badge_follows_the_details(): void
    {
        $form = $this->form();
        $key = $this->withUpload($form);

        $form->callAction($this->action($key), ['alt' => 'A red door']);
        $form->assertSee(__('image-meta::image-meta.status_ok'))->assertSee('A red door')->assertDontSee(__('image-meta::image-meta.status_missing'));

        $form->callAction($this->action($key), ['decorative' => true]);
        $form->assertSee(__('image-meta::image-meta.status_decorative'));
    }

    public function test_a_focal_point_is_badged(): void
    {
        $form = $this->form(['focalPoint' => true]);
        $key = $this->withUpload($form);

        $form->assertDontSee(__('image-meta::image-meta.status_focal'));

        $form->callAction($this->action($key), ['focal' => ['x' => 20, 'y' => 30]]);

        $form->assertSee(__('image-meta::image-meta.status_focal'));
    }

    public function test_without_alt_there_is_no_alt_badge(): void
    {
        $form = $this->form(['alt' => false, 'caption' => true]);
        $this->withUpload($form);

        $form->assertDontSee(__('image-meta::image-meta.status_missing'));
    }

    public function test_a_file_that_is_not_an_image_gets_no_row(): void
    {
        $form = $this->form();
        $form->set('data.photo', [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]);

        $form->assertDontSee(__('image-meta::image-meta.edit_details'));
    }

    /**
     * @return array<string, mixed>
     */
    private function modalFields(Testable $form): array
    {
        $schema = (fn () => $this->getMountedActionSchema())->call($form->instance());

        return $schema->getFlatFields(withHidden: true);
    }

    public function test_the_modal_has_exactly_the_requested_fields(): void
    {
        $form = $this->form(['locales' => ['en', 'uk'], 'caption' => true]);
        $key = $this->withUpload($form);

        $form->mountAction($this->action($key));

        $this->assertEqualsCanonicalizing(
            ['decorative', 'alt.en', 'alt.uk', 'caption.en', 'caption.uk'],
            array_keys($this->modalFields($form)),
        );
    }

    public function test_a_language_less_field_has_single_inputs(): void
    {
        $form = $this->form(['title' => true, 'focalPoint' => true]);
        $key = $this->withUpload($form);

        $form->mountAction($this->action($key));

        $this->assertEqualsCanonicalizing(['decorative', 'alt', 'title', 'focal'], array_keys($this->modalFields($form)));
    }

    public function test_the_modal_has_a_working_focal_point_picker_for_a_stored_file(): void
    {
        $post = new Post;
        $post->forceFill(['photo' => 'posts/a.jpg'])->save();
        Storage::disk('public')->put('posts/a.jpg', UploadedFile::fake()->image('a.jpg')->getContent());

        $form = $this->form(['focalPoint' => true], $post->refresh());
        $key = (string) array_key_first($form->get('data.photo'));

        $form->mountAction($this->action($key));

        $picker = $this->modalFields($form)['focal'];

        $this->assertInstanceOf(FocalPointPicker::class, $picker);
        $this->assertNotNull($picker->getImageUrl());
        $this->assertStringContainsString('posts/a.jpg', (string) $picker->getImageUrl());

        view()->share('errors', new ViewErrorBag);

        $html = $picker->toHtml();

        $this->assertStringContainsString('role="slider"', $html);
        $this->assertStringContainsString('imageMetaFocalPoint', $html);
        $this->assertStringContainsString('x-load-src', $html);
        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('x-on:load="fit()"', $html);
    }

    public function test_the_focal_marker_style_is_an_object_so_alpine_keeps_its_static_styles(): void
    {
        // Browser regression: a string from x-bind:style replaced the whole attribute, dropping
        // `position:absolute` from the marker (invisible), and the wrapper was wider than the picture.
        $js = (string) file_get_contents(__DIR__.'/../../resources/js/focal-point.js');
        $dist = (string) file_get_contents(__DIR__.'/../../resources/dist/focal-point.js');

        $this->assertMatchesRegularExpression('/markerStyle\(\)\s*\{\s*return \{/', $js);
        $this->assertStringNotContainsString('return `left:', $js);
        $this->assertStringContainsString('ResizeObserver', $dist);
    }

    public function test_the_decorative_toggle_can_be_left_out(): void
    {
        $form = $this->form(['decorative' => false]);
        $key = $this->withUpload($form);

        $form->mountAction($this->action($key));

        $this->assertArrayNotHasKey('decorative', $this->modalFields($form));
    }

    public function test_a_language_less_alt_from_before_locales_were_added_fills_the_first_language(): void
    {
        $post = new Post;
        $post->forceFill(['photo' => 'posts/a.jpg', 'photo_meta' => ['posts/a.jpg' => ['alt' => 'Old alt']]])->save();
        Storage::disk('public')->put('posts/a.jpg', 'x');

        $form = $this->form(['locales' => ['en', 'uk']], $post->refresh());
        $key = (string) array_key_first($form->get('data.photo'));

        $form->mountAction($this->action($key))->assertActionDataSet(['alt' => ['en' => 'Old alt', 'uk' => '']]);
    }

    public function test_the_focal_point_script_is_registered_as_an_alpine_component(): void
    {
        $src = FilamentAsset::getAlpineComponentSrc(ImageMetaServiceProvider::FOCAL_POINT, ImageMetaServiceProvider::PACKAGE);

        $this->assertNotSame('', $src);
        $this->assertFileExists(dirname(__DIR__, 2).'/resources/dist/focal-point.js');
        $this->assertStringContainsString('imageMetaFocalPoint', $this->minified());
    }

    private function minified(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/resources/dist/focal-point.js')
            .(string) file_get_contents(dirname(__DIR__, 2).'/resources/js/focal-point.js');
    }

    public function test_the_picker_without_an_image_says_so(): void
    {
        $html = FocalPointPicker::make('focal')->imageUrl(null)->getImageUrl();

        $this->assertNull($html);
    }

    public function test_the_row_previews_the_alt_text_of_a_language_the_field_manages(): void
    {
        app()->setLocale('en');

        $form = $this->form(['locales' => ['uk', 'pl']]);
        $key = $this->withUpload($form);
        $form->callAction($this->action($key), ['alt' => ['uk' => 'Двері', 'pl' => 'Drzwi']]);

        $form->assertSee('Двері')->assertDontSee('Drzwi');
    }

    public function test_the_row_does_not_preview_another_language_through_the_fallback(): void
    {
        app()->setLocale('en');
        config(['image-meta.fallback_locale' => 'pl']);

        $form = $this->form(['locales' => ['uk', 'pl'], 'requiredLocales' => ['uk']]);
        $key = $this->withUpload($form);
        $form->callAction($this->action($key), ['alt' => ['uk' => '', 'pl' => 'Drzwi']]);

        $form->assertDontSee('Drzwi');
    }
}
