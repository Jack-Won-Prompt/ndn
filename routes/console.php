<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 파기 스케줄 (CLAUDE.md §7-7): 매일 새벽 90일 경과 근로자 민감 필드 파기
Schedule::command('workers:purge-expired')->dailyAt('03:10');
