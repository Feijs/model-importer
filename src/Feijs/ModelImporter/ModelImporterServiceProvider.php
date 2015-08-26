<?php

namespace Feijs\ModelImporter;

use Illuminate\Support\ServiceProvider;

/**
 * Laravel ModelImporter Service Provider.
 * 
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class ModelImporterServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        // Boot the package
        $this->package('feijs/model-importer');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Bind & dependency inject the model importer class
        $this->app->bind('model-importer', function ($app) {
            return $this->app->make('Feijs\ModelImporter\ModelImporter');
        });

        $this->app->bind('model-importer.distributor', function ($app) {
            return $this->app->make('Feijs\ModelImporter\ModelImporter\Queue\Distributor');
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['model-importer', 'model-importer.distributor'];
    }
}
