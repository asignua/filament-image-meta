@php
    $rows = $getRows();
    $editAction = $getAction(\Asignua\FilamentImageMeta\Forms\ImageMetaPanel::ACTION);
    $previewLocale = $field->previewLocale();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
@if (count($rows) > 0)
    <ul
        class="fi-image-meta-panel"
        role="list"
        style="display:flex;flex-direction:column;gap:0.5rem;margin:0;padding:0;list-style:none"
    >
        @foreach ($rows as $row)
            <li
                wire:key="{{ $getLivewireKey() }}.row.{{ $row['key'] }}"
                class="fi-section"
                style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem"
            >
                @if ($row['url'])
                    <img
                        src="{{ $row['url'] }}"
                        alt=""
                        loading="lazy"
                        style="width:3rem;height:3rem;flex:none;object-fit:cover;border-radius:0.375rem"
                    />
                @endif

                <span style="min-width:0;flex:1 1 auto">
                    <span
                        style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500"
                    >
                        {{ $row['name'] }}
                    </span>

                    @if ($row['meta']->ownAlt($previewLocale) !== null)
                        <span
                            class="fi-fo-field-wrp-hint"
                            style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        >
                            {{ $row['meta']->ownAlt($previewLocale) }}
                        </span>
                    @endif
                </span>

                @if ($row['status'] !== 'none')
                    <x-filament::badge
                        :color="match ($row['status']) { 'ok' => 'success', 'decorative' => 'gray', default => 'warning' }"
                        :icon="match ($row['status']) { 'ok' => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle, 'decorative' => \Filament\Support\Icons\Heroicon::OutlinedEyeSlash, default => \Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle }"
                    >
                        {{ __('image-meta::image-meta.status_' . $row['status']) }}
                    </x-filament::badge>
                @endif

                @if ($row['meta']->hasFocalPoint())
                    <x-filament::badge color="gray" :icon="\Filament\Support\Icons\Heroicon::OutlinedViewfinderCircle">
                        {{ __('image-meta::image-meta.status_focal') }}
                    </x-filament::badge>
                @endif

                {{ $editAction(['key' => $row['key']]) }}
            </li>
        @endforeach
    </ul>
@endif
</x-dynamic-component>
