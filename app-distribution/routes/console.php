<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:distribution', function () {
    $this->info('App Distribution backend is installed.');
});
