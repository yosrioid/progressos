<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Configuration;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bills = Bill::ownedBy($request->user())
            ->where('is_active', true)
            ->where('is_recurring', true)
            ->orderBy('position')
            ->orderByRaw('COALESCE(due_day, 99)')
            ->get();

        return ApiResponse::collection('bills', $bills);
    }

    public function month(Request $request, string $month): JsonResponse
    {
        validator(['month' => $month], ['month' => 'required|date_format:Y-m'])->validate();

        $user = $request->user();
        $prevMonth = Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m');

        $bills = Bill::ownedBy($user)
            ->where('is_active', true)
            ->where(function ($q) use ($month) {
                $q->where('is_recurring', true)
                    ->orWhere(fn ($q2) => $q2->where('is_recurring', false)->where('month', $month));
            })
            ->with(['payments' => fn ($q) => $q->where('month', $month)])
            ->orderBy('position')
            ->orderByRaw('COALESCE(due_day, 99)')
            ->orderBy('id')
            ->get();

        $billIds = $bills->pluck('id');
        $prevPayments = BillPayment::whereIn('bill_id', $billIds)
            ->where('month', $prevMonth)
            ->where('skipped', false)
            ->get()
            ->keyBy('bill_id');

        $budgetConfig = Configuration::getValue($user, 'bills', "budget_{$month}");

        $items = $bills->map(function (Bill $bill) use ($prevPayments, $prevMonth) {
            /** @var BillPayment|null $payment */
            $payment = $bill->payments->first();
            $skipped = $payment instanceof BillPayment && $payment->skipped;
            /** @var BillPayment|null $prevPayment */
            $prevPayment = $prevPayments->get($bill->id);

            $paymentData = null;
            if ($payment instanceof BillPayment && ! $skipped) {
                $paymentData = [
                    'id' => $payment->id,
                    'actual_amount' => $payment->actual_amount,
                    'notes' => $payment->notes,
                    'paid_at' => $payment->paid_at?->toISOString(),
                ];
            }

            return [
                'id' => $bill->id,
                'name' => $bill->name,
                'estimated_amount' => $bill->estimated_amount,
                'due_day' => $bill->due_day,
                'category' => $bill->category,
                'notes' => $bill->notes,
                'is_recurring' => $bill->is_recurring,
                'paid' => $payment !== null && ! $skipped,
                'skipped' => $skipped,
                'payment' => $paymentData,
                'last_payment' => $prevPayment instanceof BillPayment ? [
                    'month' => $prevMonth,
                    'actual_amount' => $prevPayment->actual_amount,
                ] : null,
            ];
        });

        return ApiResponse::ok([
            'bills' => $items,
            'month' => $month,
            'budget' => $budgetConfig['amount'] ?? null,
        ]);
    }

    public function history(Request $request, Bill $bill): JsonResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);

        $payments = BillPayment::where('bill_id', $bill->id)
            ->where('skipped', false)
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(fn ($p) => [
                'month' => $p->month,
                'actual_amount' => $p->actual_amount,
                'notes' => $p->notes,
                'paid_at' => $p->paid_at?->toISOString(),
            ]);

        $avg = $payments->whereNotNull('actual_amount')->avg('actual_amount');

        return ApiResponse::ok([
            'bill_name' => $bill->name,
            'estimated_amount' => $bill->estimated_amount,
            'history' => $payments,
            'average_actual' => $avg ? round((float) $avg, 0) : null,
        ]);
    }

    public function annual(Request $request, string $year): JsonResponse
    {
        validator(['year' => $year], ['year' => 'required|digits:4'])->validate();

        $user = $request->user();

        $payments = BillPayment::where('user_id', $user->id)
            ->where('skipped', false)
            ->where('month', 'like', $year.'-%')
            ->get();

        $totalActual = $payments->sum(fn ($p) => (float) ($p->actual_amount ?? 0));

        $monthlyBreakdown = $payments
            ->groupBy('month')
            ->map(fn ($group) => round($group->sum(fn ($p) => (float) ($p->actual_amount ?? 0)), 0))
            ->sortKeys();

        $recurringEstimate = Bill::ownedBy($user)
            ->where('is_active', true)
            ->where('is_recurring', true)
            ->sum('estimated_amount');

        $annualEstimate = (float) $recurringEstimate * 12;

        return ApiResponse::ok([
            'year' => $year,
            'total_actual' => round($totalActual, 0),
            'annual_estimate' => round($annualEstimate, 0),
            'monthly_breakdown' => $monthlyBreakdown,
            'paid_months' => $monthlyBreakdown->count(),
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
            'is_recurring' => 'sometimes|boolean',
            'month' => ['nullable', 'date_format:Y-m', 'required_if:is_recurring,false'],
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
                'skipped' => false,
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

    public function skip(Request $request, Bill $bill): JsonResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);

        $data = $request->validate(['month' => 'required|date_format:Y-m']);

        BillPayment::updateOrCreate(
            ['bill_id' => $bill->id, 'month' => $data['month']],
            [
                'user_id' => $request->user()->id,
                'skipped' => true,
                'actual_amount' => null,
                'notes' => null,
                'paid_at' => now(),
            ]
        );

        return ApiResponse::ok([], 'Bill skipped for this month.');
    }

    public function unskip(Request $request, Bill $bill, string $month): JsonResponse
    {
        abort_if($bill->user_id !== $request->user()->id, 403);
        validator(['month' => $month], ['month' => 'required|date_format:Y-m'])->validate();

        BillPayment::where('bill_id', $bill->id)->where('month', $month)->where('skipped', true)->delete();

        return ApiResponse::ok([], 'Skip removed.');
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
