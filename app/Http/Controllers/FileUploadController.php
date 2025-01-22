<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        // Validar se o arquivo é um CSV ou Excel
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx',
        ], [
            'file.required' => 'O arquivo é obrigatório.',
            'file.mimes' => 'O arquivo deve ser do tipo CSV ou Excel.',
        ]);

        $file = $request->file('file');
        $hash = md5_file($file->getRealPath());

        // Verificar duplicação
        if (Upload::where('file_hash', $hash)->exists()) {
            return response()->json(['error' => 'O arquivo já foi enviado.'], 409);
        }

        // Salvar arquivo no sistema de arquivos com a extensão correta
        $extension = $file->getClientOriginalExtension();
        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filePath = $file->storeAs('uploads', $fileName . '.' . $extension);

        // Salvar metadados no banco de dados
        Upload::create([
            'file_name' => $file->getClientOriginalName(),
            'file_hash' => $hash,
            'file_path' => $filePath,
        ]);

        return response()->json(['message' => 'Arquivo enviado com sucesso.'], 201);
    }



    public function history(Request $request)
    {
        $query = Upload::query();

        if ($request->has('file_name')) {
            $query->where('file_name', $request->file_name);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        return response()->json($query->get());
    }

    public function search(Request $request)
    {
        $this->configureServerSettings();
        $this->validateRequest($request);

        $cacheKey = $this->generateCacheKey($request);

        // Verificar se os resultados estão no cache
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey), 200);
        }

        $fileDataBase = $this->getFileData($request->file_id);
        if (!$fileDataBase) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $filePath = $fileDataBase->file_path;
        $lines = $this->readFile($filePath);
        if (count($lines) < 1) {
            return response()->json(['error' => 'File is empty or invalid'], 400);
        }

        list($headers, $filteredData) = $this->processFileData($lines);

        $filteredResults = $this->filterData($filteredData, $request);

        if ($filteredResults->isEmpty()) {
            return response()->json([], 404);
        }

        $paginatedResults = $this->paginateResults($filteredResults, $request);

        // Armazenar os resultados no cache
        Cache::put($cacheKey, $paginatedResults, now()->addSeconds(10));

        return response()->json($paginatedResults, 200);
    }


    private function configureServerSettings()
    {
        ini_set('memory_limit', '100024M');
        ini_set('max_execution_time', '30000');
    }

    private function validateRequest(Request $request)
    {
        $request->validate([
            'file_id' => 'required|integer',
            'tckr_symb' => 'nullable|string',
            'rpt_dt' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
        ]);
    }

    private function getFileData($fileId)
    {
        return Upload::find($fileId);
    }

    private function readFile($filePath)
    {
        if (!Storage::exists($filePath)) {
            throw new \Exception('File not found');
        }

        $file = Storage::get($filePath);
        return explode("\n", $file);
    }

    private function processFileData($lines)
    {
        // Ignorar a primeira linha de status do arquivo
        $lines = array_slice($lines, 1);

        // Detectar o separador (', ou ';')
        $firstLine = trim($lines[0]);
        $separator = strpos($firstLine, ';') !== false ? ';' : ',';

        // Processar cabeçalhos com o separador identificado
        $rawHeaders = str_getcsv($firstLine, $separator);
        $headers = array_filter($rawHeaders, fn($header) => !empty($header));

        // Processar as linhas de dados com o separador identificado
        $rows = array_slice($lines, 1);
        $data = array_map(fn($line) => str_getcsv(trim($line), $separator), $rows);

        // Combinar dados com cabeçalhos
        $combinedData = array_map(function ($row) use ($headers) {
            if (count($row) !== count($headers)) {
                return null; // Ignorar linhas com número incorreto de colunas
            }

            // Combina as colunas com os cabeçalhos, removendo entradas vazias
            $rowAssoc = array_combine($headers, $row);
            return array_filter($rowAssoc, fn($key) => $key !== null && $key !== '', ARRAY_FILTER_USE_KEY);
        }, $data);

        return [$headers, array_filter($combinedData)];
    }


    private function filterData($filteredData, Request $request)
    {
        $tickerSymbol = $request->input('tckr_symb');
        $reportDate = $request->input('rpt_dt');

        return collect($filteredData)->filter(function ($item) use ($tickerSymbol, $reportDate) {
            $matchSymbol = $tickerSymbol ? (!empty($item['TckrSymb']) && strtoupper($item['TckrSymb']) === strtoupper($tickerSymbol)) : true;
            $matchDate = $reportDate ? (!empty($item['RptDt']) && Carbon::parse($item['RptDt'])->toDateString() === Carbon::parse($reportDate)->toDateString()) : true;

            return $matchSymbol && $matchDate;
        });
    }

    private function paginateResults($results, Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = 10;

        $output = $results->map(function ($item) {
            return [
                "RptDt" => $item['RptDt'] ?? 'N/A',
                "TckrSymb" => $item['TckrSymb'] ?? 'N/A',
                "MktNm" => $item['MktNm'] ?? 'N/A',
                "SctyCtgyNm" => $item['SctyCtgyNm'] ?? 'N/A',
                "ISIN" => $item['ISIN'] ?? 'N/A',
                "CrpnNm" => $item['CrpnNm'] ?? 'N/A',
            ];
        });

        $paginated = new LengthAwarePaginator(
            $output->forPage($page, $perPage)->values(),
            $output->count(),
            $perPage,
            $page
        );

        return $paginated;
    }

    private function generateCacheKey(Request $request)
    {
        return 'search_results_' . $request->file_id . '_' . md5($request->input('tckr_symb') . '_' . $request->input('rpt_dt') . '_' . $request->input('page', 1));
    }
}
