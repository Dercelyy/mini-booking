<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use Illuminate\Http\JsonResponse;

class SlotController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $now = now();
        $timezone = config('app.timezone');
        $slots = Slot::with('activeBooking:id,slot_id')->orderBy('starts_at')->get()->map(fn (Slot $slot) => [
            'id' => $slot->id,
            'starts_at' => $slot->starts_at->setTimezone($timezone)->toIso8601String(),
            'ends_at' => $slot->ends_at->setTimezone($timezone)->toIso8601String(),
            'status' => $slot->starts_at->lessThanOrEqualTo($now) ? 'past' : ($slot->activeBooking ? 'booked' : 'available'),
            'booking_id' => $slot->activeBooking?->id,
        ]);

        return response()->json(['data' => $slots, 'timezone' => $timezone, 'server_time' => $now->toIso8601String()]);
    }
}
