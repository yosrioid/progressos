<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Configuration;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bills = Bill::ownedBy($request->user())
            ->where('is_active', true)
            ->orderBy('position')
            ->orderByRaw('COALESCE(due_day, 99)')
            ->get();

        return ApiResponse::collection('bills', $bills);
    }

    public function month(Request $request, string $month): JsonResponse
    {
        validator(['month' => $month], ['month' => 'required|date_format:Y-m'])->validate();

        $user = $request->user();
        $bills = Bill::ownedBy($user)
            ->where('is_active', true)
            ->with(['payments' => fn ($q) => $q->where('month', $month)])
            ->orderBy('position')
            ->orderByRaw('COALESCE(due_day, 99)')
            ->get();

        $budgetConfig = Configuration::getValue($user, 'bills', "budget_{$month}");

        $items = $bills->map(function (Bill $bill) {
            $payment = $bill->payments->first();

            return [
                'id' => $bill->id,
                'name' => $bill->name,
                'estimated_amount' => $bill->estimated_amount,
                'due_day' => $bill->due_day,
                'category' => $bill->category,
                'notes' => $bill->notes,
                'paid' => $payment !== null,
                'payment' => $payment ? [
                    'id' => $payment->id,
                    'actual_amount' => $payment->actual_amount,
                    'notes' => $payment->notes,
                    'paid_at' => $payment->paid_at?->toISOString(),
                ] : null,
            ];
        });

        return ApiResponse::ok([
            'bills' => $items,
            'month' => $month,
            'budget' => $budgetConfig['amount'] ?? null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'estimated_amount' => 'nullable|numeric|min:0',
            'due_day' => 'nullable|integer|min:1|max:31',
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        $bill = Bill::create([...$data, 'user_id' => $request->user()->id]);

        return ApiResponse::item('bill', $bill);
    }

    public function update(Request $request, Bill $bill): JsonResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'estimated_amount' => 'nullable|numeric|min:0',
            'due_day' => 'nullable|integer|min:1|max:31',
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'sometimes|boolean',
            'position' => 'sometimes|integer|min:0',
        ]);

        $bill->update($data);

        return ApiResponse::item('bill', $bill);
    }

    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);
        $bill->delete();

        return ApiResponse::ok([], 'Bill deleted.');
    }

    public function pay(Request $request, Bill $bill): JsonResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'actual_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $payment = BillPayment::updateOrCreate(
            ['bill_id' => $bill->id, 'month' => $data['month']],
            [
                'user_id' => $request->user()->id,
                'actual_amount' => $data['actual_amount'] ?? $bill->estimated_amount,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
            ]
        );

        return ApiResponse::item('payment', [
            'id' => $payment->id,
            'actual_amount' => $payment->actual_amount,
            'notes' => $payment->notes,
            'paid_at' => $payment->paid_at?->toISOString(),
        ]);
    }

    public function unpay(Request $request, Bill $bill, string $month): JsonResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);
        validator(['month' => $month], ['month' => 'required|date_format:Y-m'])->validate();

        BillPayment::where('bill_id', $bill->id)->where('month', $month)->delete();

        return ApiResponse::ok([], 'Payment removed.');
    }

    public function setBudget(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'amount' => 'nullable|numeric|min:0',
        ]);

        Configuration::setValue($request->user(), 'bills', "budget_{$data['month']}", ['amount' => $data['amount']]);

        return ApiResponse::ok(['budget' => $data['amount']]);
    }
}
