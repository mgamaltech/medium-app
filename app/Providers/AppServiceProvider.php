<?php

namespace App\Providers;

use App\Events\ArticlePublished;
use App\Events\BookingCreated;
use App\Listeners\ClearArticleCache;
use App\Listeners\SendAuthorNotification;
use App\Listeners\SendBookingNotification;
use App\Models\Article;
use App\Observers\ArticleObserver;
use App\Policies\ArticlePolicy;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\EloquentArticleRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ArticleRepositoryInterface::class, EloquentArticleRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            ArticlePublished::class,
            SendAuthorNotification::class
        );

        Event::listen(
            ArticlePublished::class,
            ClearArticleCache::class
        );

        Event::listen(
            BookingCreated::class,
            SendBookingNotification::class
        );

        Gate::policy(Article::class, ArticlePolicy::class);
        Article::observe(ArticleObserver::class);
    }
}
