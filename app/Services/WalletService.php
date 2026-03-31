<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WalletService
{
    public function __construct(
        private UserRepository $userRepository,
        private TransactionRepository $transactionRepository,
    ) {}

    public function deposit(User $user, float $amount): Transaction
    {
        return DB::transaction(function () use ($user, $amount) {
            $this->userRepository->incrementBalance($user, $amount);

            return $this->transactionRepository->create([
                'type'        => 'deposit',
                'amount'      => $amount,
                'sender_id'   => null,
                'receiver_id' => $user->id,
                'status'      => 'completed',
            ]);
        });
    }

    public function transfer(User $sender, int $receiverId, float $amount): Transaction
    {
        if ($sender->id === $receiverId) {
            throw new HttpException(400, 'Não é possível transferir para si mesmo.');
        }

        $receiver = $this->userRepository->findById($receiverId);

        if (! $receiver) {
            throw new HttpException(404, 'Destinatário não encontrado.');
        }

        return DB::transaction(function () use ($sender, $receiver, $amount) {
            $sender->refresh();

            if ($sender->balance < $amount) {
                throw new HttpException(400, 'Saldo insuficiente.');
            }

            $this->userRepository->decrementBalance($sender, $amount);
            $this->userRepository->incrementBalance($receiver, $amount);

            return $this->transactionRepository->create([
                'type'        => 'transfer',
                'amount'      => $amount,
                'sender_id'   => $sender->id,
                'receiver_id' => $receiver->id,
                'status'      => 'completed',
            ]);
        });
    }

    public function reverse(User $user, int $transactionId): Transaction
    {
        $transaction = $this->transactionRepository->findById($transactionId);

        if (! $transaction) {
            throw new HttpException(404, 'Transação não encontrada.');
        }

        $isOwner = $transaction->sender_id === $user->id || $transaction->receiver_id === $user->id;

        if (! $isOwner) {
            throw new HttpException(403, 'Sem permissão para reverter esta transação.');
        }

        if ($transaction->status === 'reversed') {
            throw new HttpException(400, 'Transação já foi revertida.');
        }

        if ($transaction->type === 'reverse') {
            throw new HttpException(400, 'Não é possível reverter uma reversão.');
        }

        return DB::transaction(function () use ($user, $transaction) {
            if ($transaction->type === 'deposit') {
                $receiver = $this->userRepository->findById($transaction->receiver_id);
                if ($receiver->balance < $transaction->amount) {
                    throw new HttpException(400, 'Saldo insuficiente para reverter o depósito.');
                }
                $this->userRepository->decrementBalance($receiver, $transaction->amount);
            } elseif ($transaction->type === 'transfer') {
                $receiver = $this->userRepository->findById($transaction->receiver_id);
                if ($receiver->balance < $transaction->amount) {
                    throw new HttpException(400, 'Saldo insuficiente para reverter a transferência.');
                }
                $this->userRepository->decrementBalance($receiver, $transaction->amount);
                $sender = $this->userRepository->findById($transaction->sender_id);
                $this->userRepository->incrementBalance($sender, $transaction->amount);
            }

            $this->transactionRepository->markAsReversed($transaction);

            return $this->transactionRepository->create([
                'type'         => 'reverse',
                'amount'       => $transaction->amount,
                'sender_id'    => $transaction->receiver_id,
                'receiver_id'  => $transaction->sender_id,
                'status'       => 'completed',
                'reference_id' => $transaction->id,
            ]);
        });
    }
}
