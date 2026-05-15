<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('app:about-sitescout', function (): void {
    $this->info('SiteScout backend scaffold is ready.');
});
