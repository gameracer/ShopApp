<?php

namespace App\Http\Controllers;

use App\Support\ProductExporter;
use App\Support\ProductImporter;
use Illuminate\Http\Request;
use RuntimeException;

class ProductImportController extends Controller
{
    public function create()
    {
        return view('products.import', [
            'columns' => ProductImporter::COLUMNS,
            'maxFileKb' => ProductImporter::MAX_FILE_KB,
            'result' => session('import_result'),
        ]);
    }

    public function store(Request $request, ProductImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:'.ProductImporter::MAX_FILE_KB],
        ], [
            'file.extensions' => __('Choose a CSV or Excel (.xlsx) file.'),
            'file.max' => __('The file must not be larger than :size MB.', ['size' => ProductImporter::MAX_FILE_KB / 1024]),
            'file.uploaded' => __('The file could not be uploaded. It must not be larger than :size MB.', ['size' => ProductImporter::MAX_FILE_KB / 1024]),
        ], ['file' => __('File')]);

        $file = $request->file('file');

        try {
            $result = $importer->import($file->getRealPath(), $request->user(), strtolower($file->getClientOriginalExtension()) === 'xlsx');
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $message = trans_choice(':count product imported.|:count products imported.', $result['imported']);
        if ($result['stocked'] > 0) {
            $message .= ' '.trans_choice('Stock added to :count product.|Stock added to :count products.', $result['stocked']);
        }

        return redirect()->route('products.import')
            ->with('success', $message)
            ->with('import_result', $result);
    }

    /**
     * Every product with its stock, as CSV or Excel, in the layout the import reads back.
     */
    public function export(Request $request, ProductExporter $exporter)
    {
        $name = 'products-'.now()->format('Y-m-d');

        if ($request->query('format') === 'xlsx') {
            $path = tempnam(sys_get_temp_dir(), 'shopapp-export-');
            $exporter->writeXlsx($path);

            return response()->download($path, $name.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        }

        return response()->streamDownload(fn () => $exporter->writeCsv(), $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A ready-to-fill CSV with the expected header row and one example line.
     */
    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ProductImporter::COLUMNS, ',', '"', '');
            fputcsv($out, ['Soda 500ml', '6001234567890', 'bottle', '800', '1000', '24', '', '6',now()->addYear()->toDateString(), 'drinks; soda', ''], ',', '"', '');
            fclose($out);
        }, 'products-template.csv', ['Content-Type' => 'text/csv']);
    }
}
