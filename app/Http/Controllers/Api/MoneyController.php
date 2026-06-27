<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MoneyTransaction;
use App\Services\XlsxParser;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MoneyController extends Controller
{
    public function months(Request $request): JsonResponse
    {
        $user = $request->user();

        $months = MoneyTransaction::ownedBy($user)
            ->selectRaw("DATE_FORMAT(transacted_at, '%Y-%m') AS month,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense,
                COUNT(*) AS total_count")
            ->groupByRaw("DATE_FORMAT(transacted_at, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(transacted_at, '%Y-%m') DESC")
            ->get()
            ->map(fn ($m) => [
                'month' => $m->month,
                'income' => (float) $m->income,
                'expense' => (float) $m->expense,
                'net' => (float) $m->income - (float) $m->expense,
                'total_count' => (int) $m->total_count,
            ]);

        return ApiResponse::ok(['months' => $months]);
    }

    public function month(Request $request, string $month): JsonResponse
    {
        validator(['month' => $month], ['month' => 'required|date_format:Y-m'])->validate();

        $user = $request->user();

        $transactions = MoneyTransaction::ownedBy($user)
            ->whereRaw("DATE_FORMAT(transacted_at, '%Y-%m') = ?", [$month])
            ->orderBy('transacted_at', 'desc')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'transacted_at' => $t->transacted_at->toISOString(),
                'date' => $t->transacted_at->toDateString(),
                'account' => $t->account,
                'category' => $t->category,
                'subcategory' => $t->subcategory,
                'description' => $t->description,
                'amount' => (float) $t->amount,
                'type' => $t->type,
                'currency' => $t->currency,
            ]);

        $byType = $transactions->groupBy('type');
        $income = $byType->get('income', collect())->sum('amount');
        $expense = $byType->get('expense', collect())->sum('amount');

        $categories = $transactions
            ->whereIn('type', ['income', 'expense'])
            ->groupBy('category')
            ->map(fn ($items) => [
                'category' => $items->first()['category'],
                'type' => $items->first()['type'],
                'total' => $items->sum('amount'),
                'count' => $items->count(),
                'subcategories' => $items->groupBy('subcategory')->map(fn ($sub) => [
                    'subcategory' => $sub->first()['subcategory'],
                    'total' => $sub->sum('amount'),
                    'count' => $sub->count(),
                ])->values(),
            ])
            ->sortByDesc('total')
            ->values();

        return ApiResponse::ok([
            'month' => $month,
            'transactions' => $transactions,
            'summary' => [
                'income' => round($income, 0),
                'expense' => round($expense, 0),
                'net' => round($income - $expense, 0),
                'total_count' => $transactions->count(),
                'expense_count' => $byType->get('expense', collect())->count(),
                'income_count' => $byType->get('income', collect())->count(),
                'transfer_count' => ($byType->get('transfer_in', collect())->count() + $byType->get('transfer_out', collect())->count()),
            ],
            'categories' => $categories,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,zip|max:10240',
        ]);

        $path = $request->file('file')->getRealPath();
        $user = $request->user();

        $parser = new XlsxParser;
        $rawRows = $parser->parse($path);

        $typeMap = [
            'Exp.' => 'expense',
            'Income' => 'income',
            'Transfer-In' => 'transfer_in',
            'Transfer-Out' => 'transfer_out',
        ];

        $typeValues = array_keys($typeMap);
        $imported = 0;
        $duplicates = 0;
        $skipped = 0;

        DB::transaction(function () use ($rawRows, $user, $typeMap, $typeValues, &$imported, &$duplicates, &$skipped) {
            foreach ($rawRows as $row) {
                $serial = $row[0] ?? '';
                if (! is_numeric($serial)) {
                    $skipped++;

                    continue;
                }

                // Determine layout: normal (type at G=col6) or transfer (type at F=col5)
                if (isset($row[6]) && in_array($row[6], $typeValues)) {
                    $rawType = $row[6];
                    $amount = (float) ($row[5] ?? 0);
                    $category = $row[2] ?? '';
                    $subcategory = $row[4] ?? null;
                    $description = $row[7] ?? null;
                } elseif (isset($row[5]) && in_array($row[5], $typeValues)) {
                    $rawType = $row[5];
                    $amount = (float) ($row[4] ?? 0);
                    $category = 'Transfer';
                    $subcategory = $row[3] ?? ($row[2] ?? null);
                    $description = null;
                } else {
                    $skipped++;

                    continue;
                }

                if ($amount <= 0) {
                    $skipped++;

                    continue;
                }

                $type = $typeMap[$rawType];
                $account = $row[1] ?? '';
                $currency = $row[9] ?? 'IDR';

                // Excel serial → datetime (UTC)
                $unixTs = ((float) $serial - 25569) * 86400;
                $transactedAt = Carbon::createFromTimestampUTC((int) $unixTs)->toDateTimeString();

                $hash = hash('sha256', implode('|', [
                    $user->id, $serial, $account, $category,
                    $subcategory ?? '', $amount, $type,
                ]));

                $exists = MoneyTransaction::where('user_id', $user->id)
                    ->where('import_hash', $hash)
                    ->exists();

                if ($exists) {
                    $duplicates++;

                    continue;
                }

                MoneyTransaction::create([
                    'user_id' => $user->id,
                    'period_serial' => $serial,
                    'transacted_at' => $transactedAt,
                    'account' => $account,
                    'category' => $category,
                    'subcategory' => $subcategory ?: null,
                    'description' => $description ?: null,
                    'amount' => $amount,
                    'type' => $type,
                    'currency' => $currency,
                    'import_hash' => $hash,
                ]);
                $imported++;
            }
        });

        return ApiResponse::ok([
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
        ]);
    }
}
