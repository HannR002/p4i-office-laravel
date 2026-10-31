<?php

namespace App\Providers;

use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Policies\LetterPolicy;
use App\Policies\LetterTemplatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Letter::class, LetterPolicy::class);
        Gate::policy(LetterTemplate::class, LetterTemplatePolicy::class);
    }
}
