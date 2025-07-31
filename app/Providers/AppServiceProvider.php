<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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
        Transaction::observe(TransactionObserver::class);
        
        // Register policies
        Gate::policy(Order::class, OrderPolicy::class);
        
        // Add Backpack menu items
        $this->addBackpackMenuItems();
    }

    /**
     * Add custom menu items to Backpack sidebar
     */
    protected function addBackpackMenuItems(): void
    {
        // For now, we'll skip the automatic menu addition to avoid breaking the admin
        // The Business Users CRUD is still accessible via direct URL: /admin/business-user
        // TODO: Add proper menu integration later
    }
}
