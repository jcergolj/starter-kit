<?php

namespace App\Providers;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class TranslationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Lang::handleMissingKeysUsing(function (string $key, array $replacements, string $locale) {
            if (! App::environment(['production', 'staging'])) {
                return;
            }

            $skipMissingTranslationsFor = [
                'validation.custom.',
                'validation.values.',
                '(and :count more',
            ];

            if (Str::of($key)->startsWith($skipMissingTranslationsFor)) {
                return;
            }

            Bugsnag::notifyError('missing-translation', __('Missing translation key [:key], [:locale] detected.', ['key' => $key, 'locale' => $locale]));

            return $key;
        });
    }
}
