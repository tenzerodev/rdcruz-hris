<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\Registry;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** CSV import: instructions + template → upload → preview (nothing saved) → import. */
class ImportController extends Controller
{
    private const MAX_ROWS = 5000;

    public function index()
    {
        return view('admin.imports.index', ['importers' => Registry::all()]);
    }

    public function show(string $key)
    {
        return view('admin.imports.show', ['importer' => Registry::get($key), 'result' => session('import_result')]);
    }

    public function template(string $key)
    {
        $importer = Registry::get($key);

        return response()->streamDownload(function () use ($importer) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel opens it as UTF-8 (ñ, é)
            fputcsv($out, $importer->headers());
            foreach ($importer->exampleRows() as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "template-{$key}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function upload(Request $request, string $key)
    {
        $importer = Registry::get($key);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:csv,txt']], ['file.mimes' => 'Upload a .csv file (in Excel: File → Save As → CSV UTF-8).']);

        $parsed = $importer->parse(file_get_contents($request->file('file')->getRealPath()));
        if ($parsed['missing']) {
            return back()->withErrors(['file' => 'Missing required column(s): '.implode(', ', $parsed['missing']).'. Download the template and keep its first row (the headers).']);
        }
        if (! $parsed['rows']) {
            return back()->withErrors(['file' => 'The file has no data rows under the headers.']);
        }
        if (count($parsed['rows']) > self::MAX_ROWS) {
            return back()->withErrors(['file' => 'Up to '.self::MAX_ROWS.' rows per file. Split it into smaller files.']);
        }

        $valid = [];
        $errors = [];
        foreach ($parsed['rows'] as $raw) {
            [$row, $rowErrors] = $importer->validate($raw);
            $row['_line'] = $raw['_line'];
            $rowErrors ? $errors[$raw['_line']] = $rowErrors : $valid[] = $row;
        }
        foreach ($importer->fileChecks($valid) as $line => $msgs) {
            $errors[$line] = array_merge($errors[$line] ?? [], $msgs);
        }
        $valid = array_values(array_filter($valid, fn ($r) => ! isset($errors[$r['_line']])));
        ksort($errors);

        $token = (string) Str::uuid();
        Storage::disk('local')->put("imports/$token.json", json_encode([
            'key' => $key, 'user' => $request->user()->id, 'file' => $request->file('file')->getClientOriginalName(),
            'total' => count($parsed['rows']), 'valid' => $valid, 'errors' => $errors, 'unknown' => $parsed['unknown'],
        ]));

        return redirect()->route('admin.imports.preview', [$key, $token]);
    }

    public function preview(Request $request, string $key, string $token)
    {
        $data = $this->load($request, $key, $token);

        return view('admin.imports.preview', ['importer' => Registry::get($key), 'data' => $data, 'token' => $token]);
    }

    public function run(Request $request, string $key, string $token)
    {
        $importer = Registry::get($key);
        $data = $this->load($request, $key, $token);
        abort_if(! $data['valid'], 422, 'There are no valid rows to import.');

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        DB::transaction(function () use ($importer, $data, &$stats) {
            foreach ($data['valid'] as $row) {
                $stats[$importer->save($row)]++;
            }
            $importer->finish($data['valid']);
        });
        Storage::disk('local')->delete("imports/$token.json");
        AuditLog::record('import.'.$key, null, $stats + ['file' => $data['file'], 'rows_with_errors' => count($data['errors'])]);

        return redirect()->route('admin.imports.show', $key)->with('import_result', $stats + ['file' => $data['file'], 'not_imported' => count($data['errors'])]);
    }

    public function cancel(Request $request, string $key, string $token)
    {
        $this->load($request, $key, $token);
        Storage::disk('local')->delete("imports/$token.json");

        return redirect()->route('admin.imports.show', $key)->with('status', 'Import cancelled. Nothing was saved.');
    }

    private function load(Request $request, string $key, string $token): array
    {
        abort_unless(Str::isUuid($token) && Storage::disk('local')->exists("imports/$token.json"), 404, 'This upload has expired. Please upload the file again.');
        $data = json_decode(Storage::disk('local')->get("imports/$token.json"), true);
        abort_unless($data['key'] === $key && $data['user'] === $request->user()->id, 403);

        return $data;
    }
}
