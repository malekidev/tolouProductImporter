<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImportItemJob;
use App\Models\Import;
use App\Models\ImportItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        $path = $request->file('file')->store('imports');

        $import = Import::create([
            'file_path' => $path,
            'status' => 'processing',
        ]);

        $rows = $this->readCsv(Storage::path($path));

        $import->update(['total_rows' => count($rows)]);

        foreach ($rows as $row) {
            $item = ImportItem::create([
                'import_id' => $import->id,
                'raw_data' => $row,
                'status' => 'pending',
                'current_step' => 'ai',
            ]);

            // دیسپچ اولیه؛ اگه fail بشه، کرون بعدا retry می‌کنه
            //ProcessImportItemJob::dispatch($item);
        }

        return response()->json([
            'import_id' => $import->id,
            'total_rows' => $import->total_rows,
        ], 201);
    }

    private function readCsv(string $fullPath): array
    {
        $handle = fopen($fullPath, 'r');
        $header = fgetcsv($handle);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $row);
        }

        fclose($handle);

        return $rows;
    }
}
