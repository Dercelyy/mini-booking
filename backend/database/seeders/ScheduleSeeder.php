<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $slots = [];
        for ($day = 0; $day < config('booking.days'); $day++) {
            for ($hour = config('booking.opening_hour'); $hour < config('booking.closing_hour'); $hour++) {
                $start = $today->addDays($day)->setHour($hour);
                $slots[] = ['starts_at' => $start->toIso8601String(), 'ends_at' => $start->addHour()->toIso8601String()];
            }
        }
        DB::table('slots')->insertOrIgnore($slots);
    }
}
