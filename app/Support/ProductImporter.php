<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StockEntry;
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
    /** Largest CSV accepted, in kilobytes (15 MB): the app runs on capable desktops. */
    public const MAX_FILE_KB = 15 * 1024;

    /** A full 15 MB file of typical rows is ~115k lines (about 30 s to import); short rows are capped here. */
    public const MAX_ROWS = 200000;

    /** Skipped rows listed back to the user; the rest are only counted (keeps the session small). */
    public const MAX_SKIPPED_LISTED = 500;

    /** Products saved per multi-row insert (11 columns each, well under SQLite's bound-parameter limit). */
    private const BATCH_SIZE = 250;

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
     * @return array{imported: int, skipped: list<array{row: int, name: string, reason: string}>, skipped_total: int}
     *
     * @throws RuntimeException when the file cannot be read, has no name column or has too many rows
     */
    public function import(string $path, User $user): array
    {
        // Large files take a while on a single-threaded local server: never let
        // PHP's limits stop the import halfway (the transaction would roll back anyway).
        @set_time_limit(0);
        $this->ensureMemoryLimit(1024 * 1024 * 1024);

        // Quick read-only pass first: a missing header or too many rows fails
        // in a second or two, before anything is written.
        foreach ($this->readCsv($path) as $_) {
            // just counting
        }

        $existingNames = Product::pluck('name')->mapWithKeys(fn ($n) => [Product::normalizeName($n) => true])->all();
        $existingSkus = Product::whereNotNull('sku')->pluck('sku')->mapWithKeys(fn ($s) => [mb_strtolower($s) => true])->all();
        $tagIds = [];

        $imported = 0;
        $skipped = [];
        $skippedTotal = 0;

        // Rows are streamed and saved in small batches, so memory stays flat whatever
        // the file size; one transaction keeps the import all-or-nothing.
        DB::transaction(function () use ($path, $user, &$existingNames, &$existingSkus, &$tagIds, &$imported, &$skipped, &$skippedTotal) {
            $batch = [];

            foreach ($this->readCsv($path) as $line => $row) {
                [$data, $error] = $this->validateRow($row);

                $key = $data ? Product::normalizeName($data['name']) : null;
                $skuKey = $data && $data['sku'] !== null ? mb_strtolower($data['sku']) : null;

                $error ??= match (true) {
                    isset($existingNames[$key]) => __('A product with this name already exists.'),
                    $skuKey !== null && isset($existingSkus[$skuKey]) => __('The SKU / barcode is already used by another product.'),
                    default => null,
                };

                if ($error !== null) {
                    if (++$skippedTotal <= self::MAX_SKIPPED_LISTED) {
                        $skipped[] = ['row' => $line, 'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, 255), 'reason' => $error];
                    }

                    continue;
                }

                // Reserve the name/SKU so later duplicates in the same file are skipped.
                $existingNames[$key] = true;
                if ($skuKey !== null) {
                    $existingSkus[$skuKey] = true;
                }

                $batch[] = $data;
                if (count($batch) >= self::BATCH_SIZE) {
                    $imported += $this->insertBatch($batch, $user, $tagIds);
                    $batch = [];
                }
            }

            $imported += $this->insertBatch($batch, $user, $tagIds);
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'skipped_total' => $skippedTotal];
    }

    /**
     * Insert validated rows with a few multi-row queries instead of several queries per product.
     * Values go through the models' casts first, so they are stored exactly as Product::create would.
     *
     * @param  list<array<string, mixed>>  $batch
     * @param  array<string, int>  $tagIds  tag name => id, cached across batches
     */
    private function insertBatch(array $batch, User $user, array &$tagIds): int
    {
        if ($batch === []) {
            return 0;
        }

        $now = now();
        $products = [];
        foreach ($batch as $data) {
            unset($data['tags']);
            $product = (new Product)->fill($data);
            $product->setCreatedAt($now)->setUpdatedAt($now);
            $products[] = $product->getAttributes();
        }
        Product::insert($products);

        // Names are unique, so they map the new rows back to their ids.
        $ids = Product::whereIn('name', array_column($batch, 'name'))->pluck('id', 'name');

        $pivot = [];
        $stock = [];
        $note = __('Opening stock (import)');
        foreach ($batch as $data) {
            $productId = $ids[$data['name']];

            foreach ($this->tagIdsFor($data['tags'], $tagIds) as $tagId) {
                $pivot[] = ['product_id' => $productId, 'tag_id' => $tagId];
            }

            if ((float) $data['quantity'] > 0) {
                $entry = (new StockEntry)->fill([
                    'product_id' => $productId,
                    'user_id' => $user->id,
                    'quantity' => $data['quantity'],
                    'buying_price' => $data['buying_price'],
                    'selling_price' => $data['selling_price'],
                    'expiry_date' => $data['expiry_date'],
                    'note' => $note,
                ]);
                $entry->setCreatedAt($now)->setUpdatedAt($now);
                $stock[] = $entry->getAttributes();
            }
        }

        foreach (array_chunk($pivot, 1000) as $chunk) {
            DB::table('product_tag')->insert($chunk);
        }
        if ($stock !== []) {
            StockEntry::insert($stock);
        }

        return count($batch);
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, int>  $cache
     * @return list<int>
     */
    private function tagIdsFor(array $names, array &$cache): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = mb_strtolower(trim((string) $name));
            if ($name !== '') {
                $ids[] = $cache[$name] ??= Tag::idsForNames([$name])[0];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Raise PHP's memory limit to at least $bytes for this request (never lowers it). */
    private function ensureMemoryLimit(int $bytes): void
    {
        $current = trim((string) ini_get('memory_limit'));
        if ($current === '-1') {
            return;
        }

        $value = (int) $current;
        $value *= match (strtolower(substr($current, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        if ($value < $bytes) {
            @ini_set('memory_limit', (string) $bytes);
        }
    }

    /**
     * Stream the CSV as rows keyed by column name, indexed by their line number.
     *
     * @return \Generator<int, array<string, string>>
     */
    private function readCsv(string $path): \Generator
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

            $count = 0;
            $line = 1;
            while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $line++;

                if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                    continue; // blank line
                }

                if (++$count > self::MAX_ROWS) {
                    throw new RuntimeException(__('The file has too many rows. Import at most :max products at a time.', ['max' => number_format(self::MAX_ROWS)]));
                }

                $row = [];
                foreach ($headers as $i => $column) {
                    if ($column !== null && ! isset($row[$column])) {
                        $row[$column] = $this->toUtf8(trim((string) ($cells[$i] ?? '')));
                    }
                }
                yield $line => $row;
            }
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

        // Building a Laravel validator per row dominates the time of large imports, so
        // rows that clearly pass the same rules skip it; anything doubtful goes through
        // the validator below, which produces the usual (translated) error message.
        if ($this->clearlyValid($input)) {
            $validated = $input;
        } else {
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

            $validated = $validator->validated();
        }

        // Excel turns long barcodes into 6.00123E+12, losing digits for good: such a code can never scan.
        if ($input['sku'] !== null && preg_match('/^\d+([.,]\d+)?e\+?\d+$/i', $input['sku'])) {
            return [null, __('The barcode :code was damaged by Excel. Format the barcode column as Text, retype the codes and save again.', ['code' => $input['sku']])];
        }

        $expiry = null;
        if (($row['expiry_date'] ?? '') !== '') {
            $expiry = $this->parseDate($row['expiry_date']);
            if ($expiry === null) {
                return [null, __('Invalid expiry date ":value". Use YYYY-MM-DD.', ['value' => $row['expiry_date']])];
            }
        }

        return [$validated + [
            'expiry_date' => $expiry,
            'tags' => preg_split('/[;|,]/', $row['tags'] ?? '', -1, PREG_SPLIT_NO_EMPTY),
        ], null];
    }

    /**
     * Plain-PHP version of the validator rules for the common, valid row.
     * False only means "let the validator decide", never "invalid".
     */
    private function clearlyValid(array $input): bool
    {
        foreach (['buying_price', 'selling_price', 'quantity', 'reorder_level'] as $field) {
            $value = $input[$field];
            if ($value === null || ! is_numeric($value) || $value < 0) {
                return false;
            }
        }

        return $input['name'] !== '' && mb_strlen($input['name']) <= 255
            && ($input['sku'] === null || mb_strlen($input['sku']) <= 100)
            && mb_strlen($input['unit']) <= 30
            && ($input['description'] === null || mb_strlen($input['description']) <= 2000);
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
