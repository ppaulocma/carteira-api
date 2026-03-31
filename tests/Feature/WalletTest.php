<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    // DEPOSIT

    public function test_user_can_deposit(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $response = $this->actingAs($user)->postJson('/api/deposit', ['amount' => 100]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'deposit')
            ->assertJsonPath('data.amount', '100.00')
            ->assertJsonPath('data.receiver_id', $user->id);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'balance' => 100]);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_deposit_accumulates_balance(): void
    {
        $user = User::factory()->create(['balance' => 50]);

        $this->actingAs($user)->postJson('/api/deposit', ['amount' => 75]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'balance' => 125]);
    }

    public function test_deposit_fails_with_zero_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/deposit', ['amount' => 0]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_deposit_fails_with_negative_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/deposit', ['amount' => -50]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_deposit_requires_authentication(): void
    {
        $response = $this->postJson('/api/deposit', ['amount' => 100]);

        $response->assertStatus(401);
    }

    // TRANSFER

    public function test_user_can_transfer(): void
    {
        $sender   = User::factory()->create(['balance' => 500]);
        $receiver = User::factory()->create(['balance' => 0]);

        $response = $this->actingAs($sender)->postJson('/api/transfer', [
            'receiver_id' => $receiver->id,
            'amount'      => 200,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.sender_id', $sender->id)
            ->assertJsonPath('data.receiver_id', $receiver->id);

        $this->assertDatabaseHas('users', ['id' => $sender->id,   'balance' => 300]);
        $this->assertDatabaseHas('users', ['id' => $receiver->id, 'balance' => 200]);
    }

    public function test_transfer_fails_with_insufficient_balance(): void
    {
        $sender   = User::factory()->create(['balance' => 50]);
        $receiver = User::factory()->create(['balance' => 0]);

        $response = $this->actingAs($sender)->postJson('/api/transfer', [
            'receiver_id' => $receiver->id,
            'amount'      => 100,
        ]);

        $response->assertStatus(400)->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $sender->id,   'balance' => 50]);
        $this->assertDatabaseHas('users', ['id' => $receiver->id, 'balance' => 0]);
    }

    public function test_transfer_fails_to_self(): void
    {
        $user = User::factory()->create(['balance' => 500]);

        $response = $this->actingAs($user)->postJson('/api/transfer', [
            'receiver_id' => $user->id,
            'amount'      => 100,
        ]);

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_transfer_fails_when_receiver_not_found(): void
    {
        $user = User::factory()->create(['balance' => 500]);

        $response = $this->actingAs($user)->postJson('/api/transfer', [
            'receiver_id' => 9999,
            'amount'      => 100,
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_transfer_fails_with_zero_amount(): void
    {
        $sender   = User::factory()->create(['balance' => 500]);
        $receiver = User::factory()->create();

        $response = $this->actingAs($sender)->postJson('/api/transfer', [
            'receiver_id' => $receiver->id,
            'amount'      => 0,
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_transfer_requires_authentication(): void
    {
        $response = $this->postJson('/api/transfer', [
            'receiver_id' => 1,
            'amount'      => 100,
        ]);

        $response->assertStatus(401);
    }

    // REVERSE

    public function test_user_can_reverse_deposit(): void
    {
        $user        = User::factory()->create(['balance' => 200]);
        $transaction = Transaction::create([
            'type'        => 'deposit',
            'amount'      => 200,
            'receiver_id' => $user->id,
            'status'      => 'completed',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/transactions/{$transaction->id}/reverse");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'reverse')
            ->assertJsonPath('data.reference_id', $transaction->id);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'balance' => 0]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'reversed']);
    }

    public function test_user_can_reverse_transfer(): void
    {
        $sender      = User::factory()->create(['balance' => 0]);
        $receiver    = User::factory()->create(['balance' => 100]);
        $transaction = Transaction::create([
            'type'        => 'transfer',
            'amount'      => 100,
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'status'      => 'completed',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($sender)->postJson("/api/transactions/{$transaction->id}/reverse");

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', ['id' => $sender->id,   'balance' => 100]);
        $this->assertDatabaseHas('users', ['id' => $receiver->id, 'balance' => 0]);
    }

    public function test_reverse_creates_reverse_transaction(): void
    {
        $user        = User::factory()->create(['balance' => 100]);
        $transaction = Transaction::create([
            'type'        => 'deposit',
            'amount'      => 100,
            'receiver_id' => $user->id,
            'status'      => 'completed',
            'created_at'  => now(),
        ]);

        $this->actingAs($user)->postJson("/api/transactions/{$transaction->id}/reverse");

        $this->assertDatabaseHas('transactions', [
            'type'         => 'reverse',
            'amount'       => 100,
            'reference_id' => $transaction->id,
            'status'       => 'completed',
        ]);
    }

    public function test_reverse_fails_when_already_reversed(): void
    {
        $user        = User::factory()->create(['balance' => 0]);
        $transaction = Transaction::create([
            'type'        => 'deposit',
            'amount'      => 100,
            'receiver_id' => $user->id,
            'status'      => 'reversed',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/transactions/{$transaction->id}/reverse");

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_reverse_fails_when_transaction_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/transactions/9999/reverse');

        $response->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_reverse_fails_when_reversing_a_reverse(): void
    {
        $user        = User::factory()->create(['balance' => 0]);
        $transaction = Transaction::create([
            'type'        => 'reverse',
            'amount'      => 50,
            'sender_id'   => $user->id,
            'status'      => 'completed',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/transactions/{$transaction->id}/reverse");

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_reverse_fails_when_insufficient_balance_to_revert_deposit(): void
    {
        $user        = User::factory()->create(['balance' => 0]);
        $transaction = Transaction::create([
            'type'        => 'deposit',
            'amount'      => 500,
            'receiver_id' => $user->id,
            'status'      => 'completed',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/transactions/{$transaction->id}/reverse");

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_reverse_fails_when_user_is_not_owner(): void
    {
        $owner   = User::factory()->create(['balance' => 100]);
        $other   = User::factory()->create();
        $transaction = Transaction::create([
            'type'        => 'deposit',
            'amount'      => 100,
            'receiver_id' => $owner->id,
            'status'      => 'completed',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($other)->postJson("/api/transactions/{$transaction->id}/reverse");

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_reverse_requires_authentication(): void
    {
        $response = $this->postJson('/api/transactions/1/reverse');

        $response->assertStatus(401);
    }
}
