<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Asignua\FilamentImageMeta\ImageMetaUpload;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use LogicException;
use Workbench\App\Models\Post;

/**
 * An upload inside a Repeater item (JSON column, no relationship): the details live in the item itself.
 * `$options` are the named arguments of `ImageMetaUpload::make()`.
 */
class BlocksForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?Post $record = null;

    /** @var array<string, mixed> */
    public array $options = [];

    /**
     * @param array<string, mixed> $options
     */
    public function mount(?int $recordId = null, array $options = []): void
    {
        $this->options = $options;
        $this->record = $recordId ? Post::query()->findOrFail($recordId) : null;

        $this->formSchema()->fill($this->record?->attributesToArray() ?? []);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('blocks')->schema([
                    ImageMetaUpload::make(FileUpload::make('photo')->image()->disk('public')->directory('posts'), ...$this->options),
                ]),
            ])
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
