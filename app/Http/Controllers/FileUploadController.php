<?php

namespace App\Http\Controllers;

use App\Services\FileUploadService;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function upload(Request $request)
    {
        return $this->fileUploadService->getUpload($request);
    }

    public function history(Request $request)
    {
        return $this->fileUploadService->getHistory($request);
    }

    public function search(Request $request)
    {
        return $this->fileUploadService->initSearch($request);
    }
}
