<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $booking = DB::transaction(function () use ($request) {
                $slot = Slot::query()->lockForUpdate()->findOrFail($request->validated('slot_id'));
                // Recheck the clock after acquiring the lock: waiting can cross the slot boundary.
                abort_if($slot->starts_at->lessThanOrEqualTo(now()), 409, 'Этот слот уже начался. Выберите другое время.');
                abort_if($slot->activeBooking()->exists(), 409, 'Этот слот уже забронирован. Выберите другое время.');

                return Booking::create($request->validated());
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // The partial unique index also protects writes outside this controller.
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }
            abort(409, 'Этот слот уже забронирован. Выберите другое время.');
        }

        return response()->json(['id' => $booking->id], 201);
    }

    public function destroy(Booking $booking): Response
    {
        Booking::query()->whereKey($booking->id)->whereNull('cancelled_at')->update(['cancelled_at' => now()]);

        return response()->noContent();
    }
}
