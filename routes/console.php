<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function (): void {
    app(App\Services\Workflow\TriggerService::class)->triggerDueScheduledWorkflows();
})->everyMinute()->name('flowforge-scheduled-workflows')->withoutOverlapping();
