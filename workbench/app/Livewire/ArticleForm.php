<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Asignua\FilamentImageMeta\ImageMetaUpload;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use LogicException;
use Workbench\App\Models\Article;

/**
 * A SpatieMediaLibraryFileUpload form. `$options` are the arguments of `imageMeta()`.
 */
class ArticleForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?Article $record = null;

    /** @var array<string, mixed> */
    public array $options = [];

    public bool $multiple = false;

    /**
     * @param array<string, mixed> $options
     */
    public function mount(?int $recordId = null, array $options = [], bool $multiple = false): void
    {
        $this->options = $options;
        $this->multiple = $multiple;
        $this->record = $recordId ? Article::query()->findOrFail($recordId) : null;

        $this->formSchema()->fill($this->record?->attributesToArray() ?? []);
    }

    public function form(Schema $schema): Schema
    {
        $upload = SpatieMediaLibraryFileUpload::make('images')
            ->collection('images')
            ->image()
            ->disk('public')
            ->multiple($this->multiple);

        return $schema
            ->components([ImageMetaUpload::make($upload, ...$this->options)])
            ->statePath('data')
            ->model($this->record ?? Article::class);
    }

    private function formSchema(): Schema
    {
        return $this->getSchema('form') ?? throw new LogicException('The form schema is not available.');
    }

    public function save(): void
    {
        $data = $this->formSchema()->getState();

        $article = $this->record ?? new Article;
        $article->forceFill($data)->save();

        $this->record = $article;
        $this->formSchema()->model($article)->saveRelationships();
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
