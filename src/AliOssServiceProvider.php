<?php

namespace Sbing\AliOss;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use OSS\OssClient;

class AliOssServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('oss', function ($app, $config) {
            $client = new OssClient($config['access_id'], $config['access_key'], $config['endpoint']);

            return new Filesystem(new AliAdapter($client, $config));
        });
    }
}
