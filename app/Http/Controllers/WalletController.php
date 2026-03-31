<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepositRequest;
use App\Http\Requests\TransferRequest;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function deposit(DepositRequest $request): JsonResponse
    {
        $transaction = $this->walletService->deposit(
            $request->user(),
            (float) $request->amount
        );

        return response()->json([
            'success' => true,
            'data'    => $transaction,
            'message' => 'Depósito realizado com sucesso.',
        ]);
    }

    public function transfer(TransferRequest $request): JsonResponse
    {
        $transaction = $this->walletService->transfer(
            $request->user(),
            (int) $request->receiver_id,
            (float) $request->amount
        );

        return response()->json([
            'success' => true,
            'data'    => $transaction,
            'message' => 'Transferência realizada com sucesso.',
        ]);
    }

    public function reverse(Request $request, int $id): JsonResponse
    {
        $transaction = $this->walletService->reverse($request->user(), $id);

        return response()->json([
            'success' => true,
            'data'    => $transaction,
            'message' => 'Transação revertida com sucesso.',
        ]);
    }
}
