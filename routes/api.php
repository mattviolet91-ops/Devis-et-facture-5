<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\AuthentificationApi;
use Illuminate\Support\Facades\Route;

// API pour Claude : lecture et brouillons seulement (30 appels par minute et par clé).
Route::prefix('v1')->middleware([AuthentificationApi::class, 'throttle:api-claude'])->group(function () {
    Route::get('/clients', [ApiController::class, 'clients']);
    Route::get('/prestations', [ApiController::class, 'prestations']);
    Route::post('/devis/apercu', [ApiController::class, 'apercu']);
    Route::post('/devis', [ApiController::class, 'creerDevis']);
});
