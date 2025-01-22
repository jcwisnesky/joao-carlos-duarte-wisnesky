<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PaginationService
{
    public function paginate($results, $request)
    {
        $params = $request->query();

        // Verifica se apenas o parâmetro 'file_id' está presente
        if (isset($params['file_id']) && count($params) === 1) {
            // Aplica paginação
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

            return new LengthAwarePaginator(
                $output->forPage($page, $perPage)->values(),
                $output->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        // Retorna os resultados sem paginação
        return response()->json($results);
    }
}
