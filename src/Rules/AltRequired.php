<?php

declare(strict_types=1);

namespace Asignua\FilamentImageMeta\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails once per image that has neither alt text (in the required languages) nor the
 * "decorative" flag. It is implicit: the panel's state is an empty list when nothing was ever
 * described, and a rule on an empty value would not run at all.
 */
final class AltRequired implements ValidationRule
{
    /** Run on an empty value too. */
    public bool $implicit = true;

    /**
     * @param Closure(): array<int, string> $missing the names of the images that need alt text
     */
    public function __construct(private readonly Closure $missing) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (($this->missing)() as $name) {
            $fail(__('image-meta::image-meta.alt_required', ['file' => $name]));
        }
    }
}
