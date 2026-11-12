<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Imports products from a CSV file.
 *
 * Product names must be unique (case-insensitive, ignoring extra spaces):
 * rows whose name already exists in the shop, or appears earlier in the
 * same file, are skipped and reported back instead of being imported.
 */
class ProductImporter
{
    public const MAX_ROWS = 5000;

    /** Columns of the downloadable template, in order. */
    public const COLUMNS = [
        'name', 'sku', 'unit', 'buying_price', 'selling_price',
        'quantity', 'reorder_level', 'expiry_date', 'tags', 'description',
    ];

    /** Alternative header spellings mapped to the column they mean. */
    private const ALIASES = [
        'product' => 'name',
        'product_name' => 'name',
        'item' => 'name',
        'item_name' => 'name',
        'barcode' => 'sku',
        'sku_barcode' => 'sku',
        'cost' => 'buying_price',
        'cost_price' => 'buying_price',
        'buy_price' => 'buying_price',
        'price' => 'selling_price',
        'sell_price' => 'selling_price',
        'qty' => 'quantity',
        'stock' => 'quantity',
        'quantity_in_stock' => 'quantity',
        'reorder' => 'reorder_level',
        'expiry' => 'expiry_date',
        'expires' => 'expiry_date',
        'tag' => 'tags',
    ];

    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'];

    /**
     * @return array{imported: int, skipped: list<array{row: int, name: string, reason: string}>}
     *
     * @throws RuntimeException when the file cannot be read or has no name column
     */
    public function import(string $path, User $user): array
    {
        $rows = $this->readCsv($path);

        $existingNames = Product::pluck('name')->mapWithKeys(fn ($n) => [Product::normalizeName($n) => true])->all();
        $existingSkus = Product::whereNotNull('sku')->pluck('sku')->mapWithKeys(fn ($s) => [mb_strtolower($s) => true])->all();

        $valid = [];
        $skipped = [];

        foreach ($rows as $line => $row) {
            [$data, $error] = $this->validateRow($row);

            $key = $data ? Product::normalizeName($data['name']) : null;
            $skuKey = $data && $data['sku'] !== null ? mb_strtolower($data['sku']) : null;

            $error ??= match (true) {
                isset($existingNames[$key]) => __('A product with this name already exists.'),
                $skuKey !== null && isset($existingSkus[$skuKey]) => __('The SKU / barcode is already used by another product.'),
                default => null,
            };

            if ($error !== null) {
                $skipped[] = ['row' => $line, 'name' => trim((string) ($row['name'] ?? '')), 'reason' => $error];

                continue;
            }

            // Reserve the name/SKU so later duplicates in the same file are skipped.
            $existingNames[$key] = true;
            if ($skuKey !== null) {
                $existingSkus[$skuKey] = true;
            }

            $valid[] = $data;
        }

        DB::transaction(function () use ($valid, $user) {
            foreach ($valid as $data) {
                $tags = $data['tags'];
                unset($data['tags']);

                $product = Product::create($data);
                $product->tags()->sync(Tag::idsForNames($tags));

                if ((float) $product->quantity > 0) {
                    $product->stockEntries()->create([
                        'user_id' => $user->id,
                        'quantity' => $product->quantity,
                        'buying_price' => $product->buying_price,
                        'selling_price' => $product->selling_price,
                        'expiry_date' => $product->expiry_date,
                        'note' => __('Opening stock (import)'),
                    ]);
                }
            }
        });

        return ['imported' => count($valid), 'skipped' => $skipped];
    }

    /**
     * Read the CSV into rows keyed by column name, indexed by their line number.
     *
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException(__('The file could not be read.'));
        }

        try {
            $first = fgets($handle);
            if ($first === false) {
                throw new RuntimeException(__('The file is empty.'));
            }

            // Strip a UTF-8 BOM (added by Excel) and detect the delimiter;
            // Excel uses ";" in many locales.
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            $delimiter = $this->detectDelimiter($first);

            $headers = array_map(fn ($h) => $this->columnFor((string) $h), str_getcsv($first, $delimiter, '"', ''));
            if (! in_array('name', $headers, true)) {
                throw new RuntimeException(__('The file must have a header row with a "name" column. Download the template to see the expected columns.'));
            }

            $rows = [];
            $line = 1;
            while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $line++;

                if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                    continue; // blank line
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw new RuntimeException(__('The file has too many rows. Import at most :max products at a time.', ['max' => self::MAX_ROWS]));
                }

                $row = [];
                foreach ($headers as $i => $column) {
                    if ($column !== null && ! isset($row[$column])) {
                        $row[$column] = $this->toUtf8(trim((string) ($cells[$i] ?? '')));
                    }
                }
                $rows[$line] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string|null} [data, error]
     */
    private function validateRow(array $row): array
    {
        $number = fn (?string $v) => $v === null || $v === '' ? null : str_replace([',', ' '], '', $v);

        $input = [
            'name' => preg_replace('/\s+/u', ' ', $row['name'] ?? ''),
            'sku' => ($row['sku'] ?? '') === '' ? null : $row['sku'],
            'unit' => ($row['unit'] ?? '') === '' ? 'pcs' : $row['unit'],
            'buying_price' => $number($row['buying_price'] ?? null),
            'selling_price' => $number($row['selling_price'] ?? null),
            'quantity' => $number($row['quantity'] ?? null) ?? 0,
            'reorder_level' => $number($row['reorder_level'] ?? null) ?? 0,
            'description' => ($row['description'] ?? '') === '' ? null : $row['description'],
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'buying_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => __('Product name'),
            'sku' => __('SKU / Barcode'),
            'unit' => __('Unit'),
            'buying_price' => __('Buying price'),
            'selling_price' => __('Selling price'),
            'quantity' => __('Quantity'),
            'reorder_level' => __('Reorder level'),
            'description' => __('Description'),
        ]);

        if ($validator->fails()) {
            return [null, $validator->errors()->first()];
        }

        $expiry = null;
        if (($row['expiry_date'] ?? '') !== '') {
            $expiry = $this->parseDate($row['expiry_date']);
            if ($expiry === null) {
                return [null, __('Invalid expiry date ":value". Use YYYY-MM-DD.', ['value' => $row['expiry_date']])];
            }
        }

        return [$validator->validated() + [
            'expiry_date' => $expiry,
            'tags' => preg_split('/[;|,]/', $row['tags'] ?? '', -1, PREG_SPLIT_NO_EMPTY),
        ], null];
    }

    private function parseDate(string $value): ?string
    {
        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function columnFor(string $header): ?string
    {
        $key = trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($header))), '_');

        if (in_array($key, self::COLUMNS, true)) {
            return $key;
        }

        return self::ALIASES[$key] ?? null;
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
