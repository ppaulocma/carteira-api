<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Services\WalletService;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    private UserRepository&MockInterface $userRepository;
    private TransactionRepository&MockInterface $transactionRepository;
    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = Mockery::mock(UserRepository::class);
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);
        $this->service = new WalletService($this->userRepository, $this->transactionRepository);
    }

    public function test_transfer_throws_when_sender_is_receiver(): void
    {
        $sender = new User();
        $sender->id = 1;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Não é possível transferir para si mesmo.');

        $this->service->transfer($sender, 1, 100.0);
    }

    public function test_transfer_throws_when_receiver_not_found(): void
    {
        $sender = new User();
        $sender->id = 1;

        $this->userRepository
            ->shouldReceive('findById')
            ->with(2)
            ->andReturn(null);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Destinatário não encontrado.');

        $this->service->transfer($sender, 2, 100.0);
    }

    public function test_reverse_throws_when_transaction_not_found(): void
    {
        $user = new User();

        $this->transactionRepository
            ->shouldReceive('findById')
            ->with(99)
            ->andReturn(null);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Transação não encontrada.');

        $this->service->reverse($user, 99);
    }

    public function test_reverse_throws_when_already_reversed(): void
    {
        $user = new User();

        $transaction = new Transaction(['status' => 'reversed', 'type' => 'deposit']);

        $this->transactionRepository
            ->shouldReceive('findById')
            ->andReturn($transaction);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Transação já foi revertida.');

        $this->service->reverse($user, 1);
    }

    public function test_reverse_throws_when_reversing_a_reverse(): void
    {
        $user = new User();

        $transaction = new Transaction(['status' => 'completed', 'type' => 'reverse']);

        $this->transactionRepository
            ->shouldReceive('findById')
            ->andReturn($transaction);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Não é possível reverter uma reversão.');

        $this->service->reverse($user, 1);
    }
}
