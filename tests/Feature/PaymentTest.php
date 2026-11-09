<?php

namespace Tests\Feature;

use App\Models\Booking;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    private function reservation(int $price = 10000): array
    {
        $service = $this->service(['price' => $price]);
        [, $pro] = $this->barber($service);
        [$client] = $this->client();
        $id = $this->book($client, $service, $pro->id, '10:00')->json('id');

        return [$id, $client];
    }

    public function test_partial_then_full_manual_payment(): void
    {
        [$id, $client] = $this->reservation();
        $this->actingAsToken($this->admin());

        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 4000, 'method' => 'cash', 'note' => null])
            ->assertCreated()->assertJsonStructure(['id', 'reference', 'reservationId', 'reservationRef', 'clientId', 'clientName', 'amount', 'method', 'status', 'date', 'note'])
            ->assertJsonPath('status', 'partial');
        $this->assertSame('partial', Booking::query()->find($id)->payment_status->value);

        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 6001, 'method' => 'wave'])
            ->assertStatus(422)->assertJsonPath('errors.amount', 'Maximum : 6000 F CFA.');
        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 6000, 'method' => 'wave'])
            ->assertCreated()->assertJsonPath('status', 'paid');
        $this->assertSame(10000, Booking::query()->find($id)->amount_paid);
        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 1, 'method' => 'cash'])->assertStatus(409);

        $this->getJson("/api/v1/admin/reservations/{$id}")->assertJsonPath('amountPaid', 10000)->assertJsonPath('paymentStatus', 'paid');
        $this->actingAsToken($client)->getJson('/api/v1/me/payments')->assertOk()->assertJsonCount(2)
            ->assertJsonStructure([['id', 'reference', 'bookingId', 'bookingReference', 'serviceName', 'amount', 'method', 'status', 'date']]);
    }

    public function test_invalid_amounts_and_methods_are_rejected(): void
    {
        [$id] = $this->reservation();
        $this->actingAsToken($this->admin());
        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 0, 'method' => 'cash'])->assertStatus(422);
        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 'abc', 'method' => 'cash'])->assertStatus(422);
        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 100, 'method' => 'bitcoin'])->assertStatus(422);
    }

    public function test_double_submission_is_refused(): void
    {
        [$id] = $this->reservation();
        $this->actingAsToken($this->admin());
        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 2000, 'method' => 'cash'])->assertCreated();
        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 2000, 'method' => 'cash'])->assertStatus(409);
    }

    public function test_cancelled_reservation_cannot_be_paid(): void
    {
        [$id] = $this->reservation();
        $this->actingAsToken($this->admin());
        $this->postJson("/api/v1/admin/reservations/{$id}/cancel", ['reason' => 'Client absent'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->postJson('/api/v1/admin/payments', ['reservationId' => $id, 'amount' => 1000, 'method' => 'cash'])->assertStatus(409);
    }
}
