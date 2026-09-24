<?php

namespace App\Providers;

use App\Listeners\AutomationEventSubscriber;
use App\Listeners\NotificationEventSubscriber;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Observers\DealObserver;
use App\Observers\LeadObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Super Admin universally receives all permissions across gates and policies
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });

        // Register CRM Event-Driven Automation Subscriber & Notification Subscriber
        Event::subscribe(AutomationEventSubscriber::class);
        Event::subscribe(NotificationEventSubscriber::class);

        // Model Observers for Real-time Notifications
        Lead::observe(LeadObserver::class);
        Deal::observe(DealObserver::class);
    }
}
