<?php

namespace App\Repositories;

use App\Models\Transaction;

class TransactionRepository
{
    public function create(array $data): Transaction
    {
        return Transaction::create(array_merge($data, ['created_at' => now()]));
    }

    public function findById(int $id): ?Transaction
    {
        return Transaction::find($id);
    }

    public function markAsReversed(Transaction $transaction): void
    {
        $transaction->update(['status' => 'reversed']);
    }
}
