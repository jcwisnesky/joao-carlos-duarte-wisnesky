<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::middleware('auth:sanctum')->group(function(){
    Route::post('/upload', [FileUploadController::class, 'upload']);
    Route::get('/history', [FileUploadController::class, 'history']);
    Route::get('/search', [FileUploadController::class, 'search']);

});


Route::post('/login', function (Request $request) {
    $credentials = $request->only('email', 'password');

    if (Auth::attempt($credentials) === false) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $User = Auth::user();
    $token = $User->createToken('Token');
    return response()->json([$token->plainTextToken]);
});
