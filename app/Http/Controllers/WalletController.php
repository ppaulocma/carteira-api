<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepositRequest;
use App\Http\Requests\FindUserRequest;
use App\Http\Requests\ReverseTransactionRequest;
use App\Http\Requests\TransferRequest;
use App\Models\Transaction;
use App\Repositories\UserRepository;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private UserRepository $userRepository,
    ) {}

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user(),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $transactions = Transaction::with(['sender:id,name', 'receiver:id,name'])
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }

    public function findUser(FindUserRequest $request): JsonResponse
    {
        $user = $this->userRepository->findByEmail($request->email);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Usuário não encontrado.'], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Você não pode transferir para si mesmo.'], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

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

    public function reverse(ReverseTransactionRequest $request, int $id): JsonResponse
    {
        $transaction = $this->walletService->reverse($request->user(), $id);

        return response()->json([
            'success' => true,
            'data'    => $transaction,
            'message' => 'Transação revertida com sucesso.',
        ]);
    }
}
