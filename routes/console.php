<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('imports:process')
    ->everyMinute()
    ->withoutOverlapping(10) // قفل حداکثر ۱۰ دقیقه نگه داشته می‌شه
    ->onFailure(function () {
        // اینجا می‌تونی نوتیفیکیشن/لاگ اضافه کنی
    });


