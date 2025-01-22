<?php

namespace App\Services;

use App\Models\Upload;
use App\Repositories\UploadRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    protected $uploadRepository;
    protected $cacheService;
    protected $paginationService;

    public function __construct(CacheService $cacheService, PaginationService $paginationService)
    {
        $this->cacheService = $cacheService;
        $this->paginationService = $paginationService;
    }

    public function getUpload($request)
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

                $extension = $file->getClientOriginalExtension();
                $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $filePath = $file->storeAs('uploads', $fileName . '.' . $extension);

                Upload::create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_hash' => $hash,
                    'file_path' => $filePath,
                ]);

                return response()->json(['message' => 'Arquivo enviado com sucesso.'], 201);
    }

    public function getHistory($request)
    {
        $query = Upload::query();

        if ($request->has('file_name')) {
            $query->where('file_name', $request->file_name);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        return $query->get();
    }

    public function initSearch($request)
    {
        ini_set('memory_limit', '100024M');
        ini_set('max_execution_time', '30000');
        // Valida a requisição
        $this->validateRequest($request);

        // Geração do cache key
        $cacheKey = $this->cacheService->generateCacheKey($request);

        // Verificar se já está no cache
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey), 200);
        }

        // Recuperar dados do arquivo
        $fileDataBase = $this->find($request->file_id);

        // Processamento do arquivo
        $filePath = $fileDataBase->file_path;
        $lines = $this->readFile($filePath);

        list($headers, $filteredData) = $this->processFileData($lines);

        // Filtrando dados
        $filteredResults = $this->filterData($filteredData, $request);

        // Paginação
        $paginatedResults = $this->paginationService->paginate($filteredResults, $request);

        // Armazenar os resultados no cache
        Cache::put($cacheKey, $paginatedResults, now()->addSeconds(10));

        return response()->json($paginatedResults, 200);
    }

    public function find($fileId)
    {
        return Upload::find($fileId);
    }

    private function validateRequest($request)
    {
        $request->validate([
            'file_id' => 'required|integer',
            'tckr_symb' => 'nullable|string',
            'rpt_dt' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
        ]);
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

    private function filterData($filteredData, $request)
    {
        $tickerSymbol = $request->input('tckr_symb');
        $reportDate = $request->input('rpt_dt');

        return collect($filteredData)->filter(function ($item) use ($tickerSymbol, $reportDate) {
            $matchSymbol = $tickerSymbol ? (!empty($item['TckrSymb']) && strtoupper($item['TckrSymb']) === strtoupper($tickerSymbol)) : true;
            $matchDate = $reportDate ? (!empty($item['RptDt']) && Carbon::parse($item['RptDt'])->toDateString() === Carbon::parse($reportDate)->toDateString()) : true;

            return $matchSymbol && $matchDate;
        });
    }
}
