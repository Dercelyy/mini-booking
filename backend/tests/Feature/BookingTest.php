<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function futureSlot(): Slot
    {
        return Slot::create(['starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(11, 0)]);
    }

    private function payload(Slot $slot): array
    {
        return ['slot_id' => $slot->id, 'customer_name' => 'Анна Иванова', 'customer_email' => 'anna@example.com'];
    }

    public function test_future_slot_can_be_booked(): void
    {
        $slot = $this->futureSlot();
        $id = $this->postJson('/api/bookings', $this->payload($slot))->assertCreated()->json('id');
        $this->assertDatabaseHas('bookings', ['id' => $id, 'slot_id' => $slot->id, 'cancelled_at' => null]);
    }

    public function test_second_booking_is_rejected_without_creating_another_active_booking(): void
    {
        $slot = $this->futureSlot();
        $this->postJson('/api/bookings', $this->payload($slot))->assertCreated();
        $this->postJson('/api/bookings', $this->payload($slot))->assertConflict();
        $this->assertSame(1, Booking::where('slot_id', $slot->id)->whereNull('cancelled_at')->count());
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_cancellation_releases_future_slot_and_is_repeatable(): void
    {
        $slot = $this->futureSlot();
        $id = $this->postJson('/api/bookings', $this->payload($slot))->json('id');
        $this->deleteJson('/api/bookings/'.$id)->assertNoContent();
        $this->deleteJson('/api/bookings/'.$id)->assertNoContent();
        $this->postJson('/api/bookings', $this->payload($slot))->assertCreated();
        $this->assertDatabaseCount('bookings', 2);
        $this->assertSame(1, Booking::whereNull('cancelled_at')->count());
    }

    public function test_slot_starting_now_is_past_and_cannot_be_booked(): void
    {
        $this->travelTo(now()->startOfSecond());
        $slot = Slot::create(['starts_at' => now(), 'ends_at' => now()->addHour()]);
        $this->postJson('/api/bookings', $this->payload($slot))->assertConflict();
        $this->getJson('/api/slots')->assertJsonPath('data.0.status', 'past');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_validation_and_missing_booking(): void
    {
        $this->postJson('/api/bookings', ['slot_id' => 999, 'customer_name' => ' ', 'customer_email' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['slot_id', 'customer_name', 'customer_email']);
        $this->deleteJson('/api/bookings/999')->assertNotFound();
    }

    public function test_database_rejects_duplicate_active_booking_without_controller(): void
    {
        $slot = $this->futureSlot();
        Booking::create($this->payload($slot));
        $this->expectException(UniqueConstraintViolationException::class);
        Booking::create($this->payload($slot));
    }

    public function test_schedule_is_idempotent_and_preserves_bookings_when_day_changes(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        $this->seed();
        $this->assertDatabaseCount('slots', 63);
        $slot = Slot::orderBy('starts_at')->firstOrFail();
        $id = $this->postJson('/api/bookings', $this->payload($slot))->assertCreated()->json('id');
        $this->seed();
        $this->assertDatabaseCount('slots', 63);
        $this->travel(1)->days();
        $this->seed();
        $this->assertDatabaseCount('slots', 72);
        $this->assertDatabaseHas('bookings', ['id' => $id, 'cancelled_at' => null]);
        $response = $this->getJson('/api/slots')->assertOk();
        $response->assertJsonPath('data.0.booking_id', $id)->assertJsonPath('data.0.status', 'past');
        $this->assertArrayNotHasKey('customer_email', $response->json('data.0'));
        $this->assertArrayNotHasKey('customer_name', $response->json('data.0'));
        $this->assertStringEndsWith('+07:00', $response->json('data.0.starts_at'));
    }
}
