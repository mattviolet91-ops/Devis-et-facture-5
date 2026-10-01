<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use App\Http\Controllers\Auth\NouveauMotDePasseController;
use App\Http\Controllers\CorbeilleController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

Route::get('/manifest.webmanifest', [PagesController::class, 'manifest'])->name('manifest');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('login');
    Route::post('/connexion', [ConnexionController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/mot-de-passe-oublie', [MotDePasseOublieController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [MotDePasseOublieController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/nouveau-mot-de-passe/{token}', [NouveauMotDePasseController::class, 'create'])->name('password.reset');
    Route::post('/nouveau-mot-de-passe', [NouveauMotDePasseController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/accueil');
    Route::get('/accueil', [AccueilController::class, 'index'])->name('accueil');
    Route::post('/alertes/{id}/lue', [AccueilController::class, 'lireAlerte'])->name('alertes.lue');
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('logout');

    Route::get('/plus', [PagesController::class, 'plus'])->name('plus');
    Route::get('/nouveau', [PagesController::class, 'nouveau'])->name('nouveau');
    Route::get('/bientot/{rubrique}', [PagesController::class, 'bientot'])->name('bientot');

    Route::middleware('gerant')->group(function () {
        Route::get('/journal', [JournalController::class, 'index'])->name('journal');
        Route::get('/corbeille', [CorbeilleController::class, 'index'])->name('corbeille');
        Route::post('/corbeille/{type}/{id}/restaurer', [CorbeilleController::class, 'restaurer'])
            ->whereNumber('id')->name('corbeille.restaurer');
    });
});
