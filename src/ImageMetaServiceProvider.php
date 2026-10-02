<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ImageMetaServiceProvider extends PackageServiceProvider
{
    public const string PACKAGE = 'asignua/filament-image-meta';

    public const string FOCAL_POINT = 'image-meta-focal-point';

    public static string $name = 'image-meta';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        // <x-image-meta::img> and <x-image-meta::figure>
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'image-meta');

        // Fetched by Alpine's x-load, only on a page that opens the focal-point picker.
        FilamentAsset::register([
            AlpineComponent::make(self::FOCAL_POINT, __DIR__.'/../resources/dist/focal-point.js'),
        ], self::PACKAGE);
    }
}
