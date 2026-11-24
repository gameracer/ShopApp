<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportWorkbook;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request)
    {
        [$period, $date, $sellerId] = $this->params($request);
        [$start, $end] = $this->reports->range($period, $date);

        return view('reports.index', [
            'period' => $period,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'sellerId' => $sellerId,
            'sellers' => User::orderBy('name')->get(['id', 'name']),
            'report' => $this->reports->build($period, $start, $end, $sellerId),
        ]);
    }

    public function export(Request $request, ReportWorkbook $workbook)
    {
        [$period, $date, $sellerId] = $this->params($request);
        [$start, $end] = $this->reports->range($period, $date);
        $report = $this->reports->build($period, $start, $end, $sellerId);

        $filename = sprintf('report-%s-%s', $period, $start->format($period === 'yearly' ? 'Y' : ($period === 'monthly' ? 'Y-m' : 'Y-m-d')));

        if ($request->query('format') === 'xlsx') {
            $path = tempnam(sys_get_temp_dir(), 'shopapp-report-');
            $sellerName = $sellerId ? User::whereKey($sellerId)->value('name') : null;
            $workbook->write($path, $period, $start, $end, $sellerId, $sellerName, $report);

            return response()->download($path, $filename.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        }

        $filename .= '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [__('Summary')]);
            foreach ($report['summary'] as $key => $value) {
                fputcsv($out, [__(str_replace('_', ' ', ucfirst($key))), $value]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('Period'), __('Sales'), __('Revenue'), __('Gross profit'), __('Expenses'), __('Net profit')]);
            foreach ($report['breakdown'] as $row) {
                fputcsv($out, [$row['label'], $row['count'], $row['revenue'], $row['profit'], $row['expenses'], $row['net']]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('Top products'), __('Quantity'), __('Revenue'), __('Profit')]);
            foreach ($report['top_products'] as $p) {
                fputcsv($out, [$p->product_name, $p->qty, $p->revenue, $p->profit]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('Expenses by category'), __('Amount')]);
            foreach ($report['expenses_by_category'] as $category => $amount) {
                fputcsv($out, [__(ucfirst($category)), $amount]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function params(Request $request): array
    {
        $period = in_array($request->input('period'), ReportService::PERIODS, true) ? $request->input('period') : 'daily';
        $date = $request->input('date') ?: today()->toDateString();

        // <input type="month"> sends "2026-11", which Carbon parses fine; a year alone needs a full date.
        if (preg_match('/^\d{4}$/', $date)) {
            $date .= '-01-01';
        }

        return [$period, $date, $request->integer('seller') ?: null];
    }
}
