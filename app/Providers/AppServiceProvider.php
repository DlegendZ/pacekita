<?php

namespace App\Providers;

use App\Models\Club;
use App\Models\RunOccurrence;
use App\Policies\ClubPolicy;
use App\Policies\RunOccurrencePolicy;
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
        Gate::policy(Club::class, ClubPolicy::class);
        Gate::policy(RunOccurrence::class, RunOccurrencePolicy::class);
    }
}
