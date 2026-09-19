<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/bookings';

    private function makeUserWithCustomer(): User
    {
        $user = User::factory()->create();
        Customer::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function makeAvailableSlot(): Slot
    {
        return Slot::factory()->create([
            'status' => SlotStatus::AVAILABLE,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'price' => 25.00,
        ]);
    }

    public function test_it_creates_a_booking_successfully(): void
    {
        $user = $this->makeUserWithCustomer();
        $slot = $this->makeAvailableSlot();

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => $slot->id], [
                'Idempotency-Key' => 'key-123',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Booking created successfully')
            ->assertJsonPath('data.slot_id', $slot->id);

        $this->assertDatabaseHas('bookings', [
            'slot_id' => $slot->id,
            'idempotency_key' => 'key-123',
            'status' => BookingStatus::PENDING->value,
        ]);
    }

    public function test_it_reserves_the_slot_as_pending_after_booking(): void
    {
        $user = $this->makeUserWithCustomer();
        $slot = $this->makeAvailableSlot();

        $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => $slot->id], [
                'Idempotency-Key' => 'key-123',
            ]);

        $this->assertDatabaseHas('slots', [
            'id' => $slot->id,
            'status' => SlotStatus::PENDING->value,
        ]);
    }

    public function test_it_returns_422_when_idempotency_key_header_is_missing(): void
    {
        $user = $this->makeUserWithCustomer();
        $slot = $this->makeAvailableSlot();

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => $slot->id]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The Idempotency-Key header is required.');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_it_returns_422_when_idempotency_key_header_is_blank(): void
    {
        $user = $this->makeUserWithCustomer();
        $slot = $this->makeAvailableSlot();

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => $slot->id], [
                'Idempotency-Key' => '   ',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The Idempotency-Key header is required.');
    }

    public function test_it_returns_404_when_customer_profile_not_found(): void
    {
        $user = User::factory()->create();
        $slot = $this->makeAvailableSlot();

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => $slot->id], [
                'Idempotency-Key' => 'key-123',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Customer profile not found for this user.');
    }

    public function test_it_returns_422_when_slot_id_does_not_exist(): void
    {
        $user = $this->makeUserWithCustomer();

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => 999999], [
                'Idempotency-Key' => 'key-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('slot_id');
    }

    public function test_it_returns_422_when_validation_fails_for_missing_slot_id(): void
    {
        $user = $this->makeUserWithCustomer();

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, [], [
                'Idempotency-Key' => 'key-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('slot_id');
    }

    public function test_it_returns_422_when_slot_is_not_available(): void
    {
        $user = $this->makeUserWithCustomer();
        $slot = Slot::factory()->create([
            'status' => SlotStatus::BOOKED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'price' => 25.00,
        ]);

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => $slot->id], [
                'Idempotency-Key' => 'key-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Slot is not available');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_it_returns_422_when_slot_is_already_reserved_pending(): void
    {
        $user = $this->makeUserWithCustomer();
        $slot = Slot::factory()->create([
            'status' => SlotStatus::PENDING,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'price' => 25.00,
        ]);

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => $slot->id], [
                'Idempotency-Key' => 'key-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Slot is not available');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_it_returns_the_existing_booking_when_idempotency_key_is_reused(): void
    {
        $user = $this->makeUserWithCustomer();
        $customer = $user->customer;
        $slot = $this->makeAvailableSlot();

        $existingBooking = Booking::create([
            'slot_id' => $slot->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::CONFIRMED,
            'idempotency_key' => 'repeat-key',
        ]);

        $response = $this->actingAs($user)
            ->postJson($this->endpoint, ['slot_id' => $slot->id], [
                'Idempotency-Key' => 'repeat-key',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', $existingBooking->id);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_it_requires_authentication(): void
    {
        $slot = $this->makeAvailableSlot();

        $response = $this->postJson($this->endpoint, ['slot_id' => $slot->id], [
            'Idempotency-Key' => 'key-123',
        ]);

        $response->assertStatus(401);
    }

    public function test_it_allows_rebooking_the_same_slot_after_cancellation(): void
    {
        $user = $this->makeUserWithCustomer();
        $customer = $user->customer;
        $slot = $this->makeAvailableSlot();

        Booking::create([
            'slot_id' => $slot->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::CANCELLED,
            'idempotency_key' => 'old-cancelled-key',
        ]);
        $slot->update(['status' => SlotStatus::AVAILABLE]);

        $newUser = $this->makeUserWithCustomer();

        $response = $this->actingAs($newUser)
            ->postJson($this->endpoint, ['slot_id' => $slot->id], [
                'Idempotency-Key' => 'new-fresh-key',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Booking created successfully')
            ->assertJsonPath('data.slot_id', $slot->id);

        $this->assertDatabaseCount('bookings', 2);
    }
}
