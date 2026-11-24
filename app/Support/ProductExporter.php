<?php

namespace App\Support;

use App\Models\Product;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options as XlsxWriterOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Writes every product with its stock in the import layout (ProductImporter::COLUMNS),
 * so the owner can fill in the "add_stock" column and import the same file back.
 */
class ProductExporter
{
    /** Column widths for the Excel file, in the order of ProductImporter::COLUMNS. */
    private const XLSX_WIDTHS = [32, 18, 10, 14, 14, 11, 11, 14, 13, 24, 40];

    /**
     * Stream the products as CSV to the output. Starts with a BOM so Excel reads names as UTF-8.
     */
    public function writeCsv(): void
    {
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ProductImporter::COLUMNS, ',', '"', '');

        foreach ($this->rows() as $row) {
            fputcsv($out, array_map(fn ($v) => $v === null ? '' : (string) $v, $row), ',', '"', '');
        }

        fclose($out);
    }

    /**
     * Write the products to an Excel workbook at $path. Barcodes are text cells, so
     * Excel keeps their leading zeros and never shows them as 6.00123E+12.
     */
    public function writeXlsx(string $path): void
    {
        $options = new XlsxWriterOptions;
        foreach (self::XLSX_WIDTHS as $i => $width) {
            $options->setColumnWidth($width, $i + 1);
        }

        $writer = new XlsxWriter($options);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValuesWithStyle(ProductImporter::COLUMNS, (new Style)->withFontBold(true)));

        foreach ($this->rows() as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    /**
     * @return \Generator<int, list<string|float|null>>
     */
    private function rows(): \Generator
    {
        foreach (Product::with('tags')->orderBy('name')->lazy(500) as $product) {
            yield [
                $product->name,
                $product->sku,
                $product->unit,
                (float) $product->buying_price,
                (float) $product->selling_price,
                (float) $product->quantity,
                null, // add_stock: left empty for the owner to fill in
                (float) $product->reorder_level,
                $product->expiry_date?->toDateString(),
                $product->tags->pluck('name')->implode('; ') ?: null,
                $product->description,
            ];
        }
    }
}
