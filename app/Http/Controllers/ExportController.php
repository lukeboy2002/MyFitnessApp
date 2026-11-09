<?php

namespace App\Http\Controllers;

use App\Support\DataExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Profiel: je eigen trainingen, wegingen of maten downloaden als CSV */
class ExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, DataExport::TYPES), 404);

        $user = $request->user();
        $filename = str(config('app.name'))->slug().'-'.$type.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($user, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel leest dan é, ë enz. goed

            fputcsv($out, DataExport::headers($type), ';', escape: '');
            foreach (DataExport::rows($user, $type) as $row) {
                fputcsv($out, $row, ';', escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
