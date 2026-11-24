<?php

namespace App\Support;

use App\Services\ReportService;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a report as an Excel workbook: a Summary sheet with the totals and the short
 * tables, then one sheet per long table (the period breakdown, every sale, the top
 * products and every expense), so each can be sorted and filtered on its own.
 */
class ReportWorkbook
{
    private const MONEY = '#,##0.00';

    private const QTY = '#,##0.##';

    private const DATE = 'dd/mm/yyyy';

    private const DATETIME = 'dd/mm/yyyy hh:mm';

    private Writer $writer;

    private bool $firstSheet = true;

    public function __construct(private ReportService $reports) {}

    public function write(string $path, string $period, CarbonImmutable $start, CarbonImmutable $end, ?int $sellerId, ?string $sellerName, array $report): void
    {
        $this->writer = new Writer;
        $this->writer->openToFile($path);
        $this->firstSheet = true;

        $this->summarySheet($period, $start, $sellerName, $report);
        $this->breakdownSheet($period, $report);
        $this->salesSheet($start, $end, $sellerId, $report['summary']);
        $this->topProductsSheet($report['top_products']);
        if ($sellerId === null) {
            $this->expensesSheet($start, $end, $report['summary']['expenses']);
        }

        $this->writer->close();
    }

    private function summarySheet(string $period, CarbonImmutable $start, ?string $sellerName, array $report): void
    {
        $this->sheet(__('Summary'), [30, 18, 18]);
        $s = $report['summary'];

        $title = ['daily' => __('Daily report'), 'monthly' => __('Monthly report'), 'yearly' => __('Yearly report')][$period];
        $this->row([$title], [0 => $this->bold()->withFontSize(14)]);
        $this->row([__('Period'), match ($period) {
            'yearly' => $start->format('Y'),
            'monthly' => $start->translatedFormat('F Y'),
            default => $start->translatedFormat('l, d F Y'),
        }]);
        $this->row([__('Shop'), Shop::name()]);
        $this->row([__('Seller'), $sellerName ?? __('All sellers')]);
        $this->row([__('Downloaded'), now()->toDateTimeImmutable()], [1 => $this->format(self::DATETIME)]);
        $this->row([]);

        $money = [1 => $this->format(self::MONEY)];
        $this->row([__('Sales'), $s['sales_count']]);
        $this->row([__('Revenue'), $s['revenue']], $money);
        $this->row([__('Cost of goods'), $s['cost']], $money);
        $this->row([__('Gross profit'), $s['gross_profit']], $money);
        $this->row([__('Expenses'), $s['expenses']], $money);
        $this->row([__('Net profit'), $s['net_profit']], [0 => $this->bold(), 1 => $this->bold()->withFormat(self::MONEY)]);
        $this->row([__('Money received'), $s['cash_received']], $money);
        $this->row([__('Sold on credit (unpaid)'), $s['credit_given']], $money);

        $total = [2 => $this->format(self::MONEY)];

        $this->row([]);
        $this->header([__('Payment methods'), __('Sales'), __('Total')]);
        foreach ($report['by_method'] as $method => $row) {
            $this->row([__(ucfirst($method)), $row['count'], $row['total']], $total);
        }

        $this->row([]);
        $this->header([__('Sales by seller'), __('Sales'), __('Total')]);
        foreach ($report['by_seller'] as $row) {
            $this->row([$row['name'], $row['count'], $row['total']], $total);
        }

        if ($sellerName === null) {
            $this->row([]);
            $this->header([__('Expenses by category'), __('Amount')]);
            foreach ($report['expenses_by_category'] as $category => $amount) {
                $this->row([__(ucfirst($category)), $amount], $money);
            }
        }
    }

    private function breakdownSheet(string $period, array $report): void
    {
        // Expenses are recorded per day, so a daily report's hours have none (as on the page).
        $withExpenses = $period !== 'daily';

        $this->sheet(match ($period) {
            'yearly' => __('Month by month'),
            'monthly' => __('Day by day'),
            default => __('Sales by hour'),
        }, [18, 10, 16, 16, 16, 16]);

        $this->header(array_merge(
            [__('Period'), __('Sales'), __('Revenue'), __('Gross profit')],
            $withExpenses ? [__('Expenses'), __('Net profit')] : [],
        ));

        $styles = [2 => $this->format(self::MONEY), 3 => $this->format(self::MONEY), 4 => $this->format(self::MONEY), 5 => $this->format(self::MONEY)];
        $cells = fn (array $r) => array_merge(
            [$r['label'], $r['count'], $r['revenue'], $r['profit']],
            $withExpenses ? [$r['expenses'], $r['net']] : [],
        );

        foreach ($report['breakdown'] as $row) {
            $this->row($cells($row), $styles);
        }

        $s = $report['summary'];
        $this->totalRow($cells([
            'label' => __('Total'), 'count' => $s['sales_count'], 'revenue' => $s['revenue'],
            'profit' => $s['gross_profit'], 'expenses' => $s['expenses'], 'net' => $s['net_profit'],
        ]), $styles);
    }

    private function salesSheet(CarbonImmutable $start, CarbonImmutable $end, ?int $sellerId, array $summary): void
    {
        $this->sheet(__('Sales'), [17, 16, 20, 24, 14, 14, 14, 14, 14, 14]);
        $this->header([
            __('Date'), __('Invoice'), __('Seller'), __('Customer'), __('Payment method'),
            __('Total'), __('Paid'), __('Balance'), __('Cost of goods'), __('Profit'),
        ]);

        $money = $this->format(self::MONEY);
        $styles = [0 => $this->format(self::DATETIME), 5 => $money, 6 => $money, 7 => $money, 8 => $money, 9 => $money];

        $sales = $this->reports->salesQuery($start, $end, $sellerId)
            ->with(['user:id,name', 'customer:id,name'])
            ->orderBy('sold_at')
            ->lazy(500);

        $paid = 0.0;
        foreach ($sales as $sale) {
            $paid += (float) $sale->amount_paid;
            $this->row([
                $sale->sold_at->toDateTimeImmutable(),
                $sale->invoice_no,
                $sale->user?->name,
                $sale->customer?->name,
                __(ucfirst($sale->payment_method)),
                (float) $sale->total,
                (float) $sale->amount_paid,
                round((float) $sale->total - (float) $sale->amount_paid, 2),
                (float) $sale->cost_total,
                round((float) $sale->total - (float) $sale->cost_total, 2),
            ], $styles);
        }

        $this->totalRow([
            __('Total'), null, null, null, null,
            $summary['revenue'], round($paid, 2), $summary['credit_given'], $summary['cost'], $summary['gross_profit'],
        ], $styles);
    }

    private function topProductsSheet(iterable $products): void
    {
        $this->sheet(__('Top products'), [6, 36, 12, 16, 16]);
        $this->header(['#', __('Product'), __('Quantity'), __('Revenue'), __('Profit')]);

        $styles = [2 => $this->format(self::QTY), 3 => $this->format(self::MONEY), 4 => $this->format(self::MONEY)];
        foreach (array_values(collect($products)->all()) as $i => $p) {
            $this->row([$i + 1, $p->product_name, (float) $p->qty, round((float) $p->revenue, 2), round((float) $p->profit, 2)], $styles);
        }
    }

    private function expensesSheet(CarbonImmutable $start, CarbonImmutable $end, float $total): void
    {
        $this->sheet(__('Expenses'), [13, 20, 44, 16]);
        $this->header([__('Date'), __('Category'), __('Description'), __('Amount')]);

        $styles = [0 => $this->format(self::DATE), 3 => $this->format(self::MONEY)];
        foreach ($this->reports->expensesQuery($start, $end)->orderBy('expense_date')->orderBy('id')->lazy(500) as $expense) {
            $this->row([
                $expense->expense_date->toDateTimeImmutable(),
                __(ucfirst($expense->category)),
                $expense->description,
                (float) $expense->amount,
            ], $styles);
        }

        $this->totalRow([__('Total'), null, null, $total], $styles);
    }

    /** Start a new sheet (the workbook's first sheet already exists). */
    private function sheet(string $name, array $widths): void
    {
        $sheet = $this->firstSheet ? $this->writer->getCurrentSheet() : $this->writer->addNewSheetAndMakeItCurrent();
        $this->firstSheet = false;

        // Excel sheet names: at most 31 characters, none of : \ / ? * [ ]
        $sheet->setName(mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $name), 0, 31));
        foreach ($widths as $i => $width) {
            $sheet->setColumnWidth($width, $i + 1);
        }
    }

    private function header(array $values): void
    {
        $this->writer->addRow(Row::fromValuesWithStyle($values, $this->bold()->withBackgroundColor('E2E8F0')));
    }

    /** @param array<int, Style> $styles column index => style */
    private function row(array $values, array $styles = []): void
    {
        $this->writer->addRow(Row::fromValuesWithStyles($values, $styles));
    }

    /** @param array<int, Style> $styles column index => style */
    private function totalRow(array $values, array $styles): void
    {
        $bold = array_map(fn ($i) => ($styles[$i] ?? new Style)->withFontBold(true), array_keys($values));
        $this->row($values, $bold);
    }

    private function bold(): Style
    {
        return (new Style)->withFontBold(true);
    }

    private function format(string $format): Style
    {
        return (new Style)->withFormat($format);
    }
}
