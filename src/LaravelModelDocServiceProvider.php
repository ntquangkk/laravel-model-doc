<?php

namespace TriQuang\LaravelModelDoc;

use Illuminate\Support\ServiceProvider;
use TriQuang\LaravelModelDoc\Commands\GenerateModelDocCommand;

class LaravelModelDocServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Register the command
            $this->commands([
                GenerateModelDocCommand::class,
            ]);
        }
    }

    public function register() {}
}
