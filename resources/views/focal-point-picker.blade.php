@php
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Js;

    $statePath = $getStatePath();
    $imageUrl = $getImageUrl();
    $id = $getId();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($imageUrl)
        <div
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('image-meta-focal-point', 'asignua/filament-image-meta') }}"
            x-data="imageMetaFocalPoint({
                state: $wire.$entangle({{ Js::from($statePath) }}),
                labels: {{ Js::from(['set' => __('image-meta::image-meta.focal_value', ['x' => ':x', 'y' => ':y']), 'unset' => __('image-meta::image-meta.focal_not_set')]) }},
            })"
            wire:ignore
            wire:key="{{ $getLivewireKey() }}.focal-point"
            style="display:flex;flex-direction:column;gap:0.5rem"
        >
            <div
                x-ref="surface"
                x-on:pointerdown.prevent="start($event)"
                x-on:pointermove="move($event)"
                x-on:pointerup="stop($event)"
                x-on:pointercancel="stop($event)"
                style="position:relative;display:inline-block;max-width:100%;touch-action:none;cursor:crosshair;user-select:none;line-height:0"
            >
                <img
                    src="{{ $imageUrl }}"
                    alt=""
                    draggable="false"
                    x-on:load="fit()"
                    style="display:block;max-width:100%;max-height:24rem;border-radius:0.5rem"
                />
                <span
                    id="{{ $id }}"
                    role="slider"
                    tabindex="0"
                    aria-label="{{ $getLabel() }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    x-bind:aria-valuenow="Math.round(x)"
                    x-bind:aria-valuetext="text"
                    x-on:keydown="key($event)"
                    x-bind:style="markerStyle()"
                    style="position:absolute;width:1.5rem;height:1.5rem;margin:-0.75rem 0 0 -0.75rem;border-radius:9999px;border:3px solid #fff;background:var(--primary-500,#3b82f6);box-shadow:0 0 0 2px rgba(0,0,0,.55),0 1px 6px rgba(0,0,0,.5)"
                    x-bind:data-unset="! isSet"
                ></span>
            </div>

            <div style="display:flex;align-items:center;gap:0.75rem;line-height:1.25rem">
                <span class="fi-fo-field-wrp-hint" x-text="text" aria-live="polite"></span>
                <x-filament::link tag="button" type="button" size="sm" x-show="isSet" x-cloak x-on:click="reset()">
                    {{ __('image-meta::image-meta.focal_reset') }}
                </x-filament::link>
            </div>
        </div>
    @else
        <p class="fi-fo-field-wrp-hint">{{ __('image-meta::image-meta.preview_unavailable') }}</p>
    @endif
</x-dynamic-component>
