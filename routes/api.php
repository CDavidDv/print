<?php

use App\Http\Controllers\PrintController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Print server routes (no auth required - local only)
Route::post('/print/thermal', [PrintController::class, 'printThermal']);
Route::post('/print/normal', [PrintController::class, 'printNormal']);
Route::get('/status', [PrintController::class, 'status']);
Route::get('/config', [PrintController::class, 'getConfig']);
Route::put('/config', [PrintController::class, 'updateConfig']);
Route::get('/test/chars', [PrintController::class, 'testChars']);
