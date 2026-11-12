<?php

namespace App\Http\Controllers;

use App\Support\ProductImporter;
use Illuminate\Http\Request;
use RuntimeException;

class ProductImportController extends Controller
{
    public function create()
    {
        return view('products.import', [
            'columns' => ProductImporter::COLUMNS,
            'result' => session('import_result'),
        ]);
    }

    public function store(Request $request, ProductImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [], ['file' => __('CSV file')]);

        try {
            $result = $importer->import($request->file('file')->getRealPath(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('products.import')
            ->with('success', trans_choice(':count product imported.|:count products imported.', $result['imported']))
            ->with('import_result', $result);
    }

    /**
     * A ready-to-fill CSV with the expected header row and one example line.
     */
    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ProductImporter::COLUMNS, ',', '"', '');
            fputcsv($out, ['Soda 500ml', '6001234567890', 'bottle', '800', '1000', '24', '6', now()->addYear()->toDateString(), 'drinks; soda', ''], ',', '"', '');
            fclose($out);
        }, 'products-template.csv', ['Content-Type' => 'text/csv']);
    }
}
