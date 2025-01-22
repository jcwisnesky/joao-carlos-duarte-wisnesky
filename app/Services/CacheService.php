<?php

namespace App\Services;

use Illuminate\Http\Request;

class CacheService
{
    public function generateCacheKey(Request $request)
    {
        return 'search_results_' . $request->file_id . '_' . md5($request->input('tckr_symb') . '_' . $request->input('rpt_dt') . '_' . $request->input('page', 1));
    }
}
