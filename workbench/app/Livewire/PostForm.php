<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Asignua\FilamentImageMeta\ImageMetaUpload;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use LogicException;
use Workbench\App\Models\Post;

/**
 * A plain-FileUpload form. `$options` are the named arguments of `ImageMetaUpload::make()`.
 */
class PostForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?Post $record = null;

    /** @var array<string, mixed> */
    public array $options = [];

    public bool $multiple = false;

    /**
     * `disabled` or `hidden` locks the upload itself, as an app would for a user without rights;
     * `preserve` keeps the original file names (a re-uploaded file lands on the same path).
     */
    public ?string $uploadMode = null;

    /**
     * @param array<string, mixed> $options
     */
    public function mount(?int $recordId = null, array $options = [], bool $multiple = false, ?string $uploadMode = null): void
    {
        $this->options = $options;
        $this->multiple = $multiple;
        $this->uploadMode = $uploadMode;
        $this->record = $recordId ? Post::query()->findOrFail($recordId) : null;

        $this->formSchema()->fill($this->record?->attributesToArray() ?? []);
    }

    public function form(Schema $schema): Schema
    {
        $name = $this->multiple ? 'gallery' : 'photo';

        $options = $this->options;

        // A Closure cannot travel through a Livewire property; the test asks for one by name.
        if (($options['requireAlt'] ?? null) === 'closure-false') {
            $options['requireAlt'] = static fn (): bool => false;
        }

        $upload = FileUpload::make($name)
            ->image()
            ->disk('public')
            ->directory('posts')
            ->multiple($this->multiple)
            ->preserveFilenames($this->uploadMode === 'preserve')
            ->disabled(fn (): bool => $this->uploadMode === 'disabled')
            ->hidden(fn (): bool => $this->uploadMode === 'hidden');

        return $schema
            ->components([ImageMetaUpload::make($upload, ...$options)])
            ->statePath('data')
            ->model($this->record ?? Post::class);
    }

    private function formSchema(): Schema
    {
        return $this->getSchema('form') ?? throw new LogicException('The form schema is not available.');
    }

    public function save(): void
    {
        $data = $this->formSchema()->getState();

        $post = $this->record ?? new Post;
        $post->forceFill($data)->save();

        $this->record = $post;
        $this->formSchema()->model($post)->saveRelationships();
    }

    public function render(): string
    {
        return <<<'blade'
            <div>
                {{ $this->getSchema('form') }}

                <x-filament-actions::modals />
            </div>
            blade;
    }
}
