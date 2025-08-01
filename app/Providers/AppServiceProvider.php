<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Transaction;
use App\Observers\OrderObserver;
use App\Observers\TransactionObserver;
use App\Policies\OrderPolicy;
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
        // Register model observers
        Order::observe(OrderObserver::class);
        Transaction::observe(TransactionObserver::class);
        
        // Register policies
        Gate::policy(Order::class, OrderPolicy::class);
    }
}
