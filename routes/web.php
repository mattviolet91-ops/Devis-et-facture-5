<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use App\Http\Controllers\Auth\NouveauMotDePasseController;
use App\Http\Controllers\ChantierController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\CorbeilleController;
use App\Http\Controllers\DevisController;
use App\Http\Controllers\DevisExpressController;
use App\Http\Controllers\EspaceClientController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\FichiersController;
use App\Http\Controllers\ImportClientsController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\NoteClientController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PieceJointeController;
use App\Http\Controllers\PrestationController;
use App\Http\Controllers\ReglagesController;
use App\Http\Controllers\SignatureSurPlaceController;
use App\Http\Controllers\TextesTypesController;
use App\Http\Controllers\VisionneuseController;
use App\Http\Middleware\CompteActif;
use App\Http\Middleware\ConfigurationRequise;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Ressources publiques sans session : elles ne doivent pas devenir la « page précédente ».
Route::withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
    CompteActif::class,
    ConfigurationRequise::class,
])->group(function () {
    Route::get('/manifest.webmanifest', [PagesController::class, 'manifest'])->name('manifest');
    Route::get('/theme.css', [FichiersController::class, 'theme'])->name('theme');
    Route::get('/fichiers/logo', [FichiersController::class, 'logo'])->name('fichiers.logo');
    Route::get('/fichiers/icone-{taille}.png', [FichiersController::class, 'icone'])->whereNumber('taille')->name('fichiers.icone');
});

// Pages clients (lien personnel, sans compte), aussi sur l'adresse réservée aux clients.
Route::prefix('/c/{jeton}')->middleware('throttle:60,1')->name('client.')->group(function () {
    Route::get('/', [EspaceClientController::class, 'show'])->name('document');
    Route::get('/pdf', [EspaceClientController::class, 'pdf'])->name('pdf');
    Route::post('/signer', [EspaceClientController::class, 'signer'])->middleware('throttle:10,1')->name('signer');
    Route::post('/refuser', [EspaceClientController::class, 'refuser'])->middleware('throttle:10,1')->name('refuser');
    Route::post('/modification', [EspaceClientController::class, 'modification'])->middleware('throttle:10,1')->name('modification');
});

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
    Route::get('/visionneuse', [VisionneuseController::class, 'show'])->name('visionneuse');
    Route::get('/nouveau', [PagesController::class, 'nouveau'])->name('nouveau');
    Route::get('/bientot/{rubrique}', [PagesController::class, 'bientot'])->name('bientot');

    // Catalogue : lecture pour tous, modification par le gérant.
    Route::get('/catalogue', [PrestationController::class, 'index'])->name('catalogue.index');
    Route::get('/catalogue/recherche', [PrestationController::class, 'recherche'])->name('catalogue.recherche');
    Route::middleware('gerant')->group(function () {
        Route::get('/catalogue/nouvelle', [PrestationController::class, 'create'])->name('catalogue.create');
        Route::post('/catalogue', [PrestationController::class, 'store'])->name('catalogue.store');
        Route::post('/catalogue/depart', [PrestationController::class, 'chargerDepart'])->name('catalogue.depart');
        Route::get('/catalogue/{prestation}/modifier', [PrestationController::class, 'edit'])->whereNumber('prestation')->name('catalogue.edit');
        Route::put('/catalogue/{prestation}', [PrestationController::class, 'update'])->whereNumber('prestation')->name('catalogue.update');
        Route::delete('/catalogue/{prestation}', [PrestationController::class, 'destroy'])->whereNumber('prestation')->name('catalogue.destroy');
    });

    // Devis (gérant et commercial).
    Route::get('/devis', [DevisController::class, 'index'])->name('devis.index');
    Route::get('/devis/nouveau', [DevisController::class, 'create'])->name('devis.create');
    Route::post('/devis', [DevisController::class, 'store'])->name('devis.store');
    Route::get('/devis/express', [DevisExpressController::class, 'create'])->name('devis.express');
    Route::post('/devis/express/apercu', [DevisExpressController::class, 'apercu'])->name('devis.express.apercu');
    Route::post('/devis/express', [DevisExpressController::class, 'store'])->name('devis.express.store');
    Route::prefix('/devis/{devis}')->whereNumber('devis')->group(function () {
        Route::get('/', [DevisController::class, 'show'])->name('devis.show');
        Route::get('/modifier', [DevisController::class, 'edit'])->name('devis.edit');
        Route::put('/', [DevisController::class, 'update'])->name('devis.update');
        Route::delete('/', [DevisController::class, 'destroy'])->name('devis.destroy');
        Route::post('/envoye', [DevisController::class, 'envoyer'])->name('devis.envoyer');
        Route::post('/accepte', [DevisController::class, 'accepter'])->name('devis.accepter');
        Route::post('/refuse', [DevisController::class, 'refuser'])->name('devis.refuser');
        Route::post('/nouvelle-version', [DevisController::class, 'nouvelleVersion'])->name('devis.version');
        Route::post('/dupliquer', [DevisController::class, 'dupliquer'])->name('devis.dupliquer');
        Route::get('/pdf', [DevisController::class, 'pdf'])->name('devis.pdf');
        Route::get('/signer', [SignatureSurPlaceController::class, 'create'])->name('devis.signer');
        Route::post('/signer', [SignatureSurPlaceController::class, 'store']);
        Route::post('/lien', [SignatureSurPlaceController::class, 'lien'])->name('devis.lien');
    });

    // Clients et chantiers (gérant et commercial).
    Route::get('/clients/import', [ImportClientsController::class, 'create'])->name('clients.import');
    Route::post('/clients/import', [ImportClientsController::class, 'analyser'])->middleware('throttle:10,1');
    Route::get('/clients/import/{jeton}', [ImportClientsController::class, 'apercu'])->name('clients.import.apercu');
    Route::post('/clients/import/{jeton}', [ImportClientsController::class, 'importer'])->name('clients.import.importer');
    Route::get('/clients/nouveau', [ClientController::class, 'create'])->name('clients.create');
    Route::get('/clients/{client}/modifier', [ClientController::class, 'edit'])->name('clients.edit');
    Route::resource('clients', ClientController::class)->except(['create', 'edit'])->where(['client' => '[0-9]+']);

    Route::get('/clients/{client}/chantiers/nouveau', [ChantierController::class, 'create'])->name('chantiers.create');
    Route::post('/clients/{client}/chantiers', [ChantierController::class, 'store'])->name('chantiers.store');
    Route::get('/chantiers/{chantier}/modifier', [ChantierController::class, 'edit'])->name('chantiers.edit');
    Route::put('/chantiers/{chantier}', [ChantierController::class, 'update'])->name('chantiers.update');
    Route::delete('/chantiers/{chantier}', [ChantierController::class, 'destroy'])->name('chantiers.destroy');

    Route::post('/clients/{client}/notes', [NoteClientController::class, 'store'])->name('notes.store');
    Route::delete('/notes/{note}', [NoteClientController::class, 'destroy'])->name('notes.destroy');

    Route::post('/clients/{client}/fichiers', [PieceJointeController::class, 'store'])->middleware('throttle:30,1')->name('pieces.store');
    Route::get('/fichiers/{piece}', [PieceJointeController::class, 'show'])->whereNumber('piece')->name('pieces.show');
    Route::delete('/fichiers/{piece}', [PieceJointeController::class, 'destroy'])->whereNumber('piece')->name('pieces.destroy');

    Route::middleware('gerant')->prefix('configuration')->name('configuration')->group(function () {
        Route::get('/', [ConfigurationController::class, 'index']);
        Route::post('/passer', [ConfigurationController::class, 'passer'])->name('.passer');
        Route::post('/terminer', [ConfigurationController::class, 'terminer'])->name('.terminer');
        Route::get('/devis-exemple.pdf', [ConfigurationController::class, 'pdfExemple'])->name('.pdf');
        Route::post('/emails/test', [ReglagesController::class, 'testerEmail'])->middleware('throttle:5,1')->name('.test-email');
        Route::post('/{etape}/plus-tard', [ConfigurationController::class, 'plusTard'])->name('.plus-tard');
        Route::get('/{etape}', [ConfigurationController::class, 'edit'])->name('.etape');
        Route::put('/{etape}', [ConfigurationController::class, 'update'])->name('.enregistrer');
    });

    // Factures et avoirs : gérant seulement.
    Route::middleware('gerant')->group(function () {
        Route::get('/factures', [FactureController::class, 'index'])->name('factures.index');
        Route::get('/factures/nouvelle', [FactureController::class, 'create'])->name('factures.create');
        Route::post('/factures', [FactureController::class, 'store'])->name('factures.store');
        Route::post('/devis/{devis}/facturer', [FactureController::class, 'depuisDevis'])->whereNumber('devis')->name('factures.depuis-devis');
        Route::prefix('/factures/{facture}')->whereNumber('facture')->group(function () {
            Route::get('/', [FactureController::class, 'show'])->name('factures.show');
            Route::get('/modifier', [FactureController::class, 'edit'])->name('factures.edit');
            Route::put('/', [FactureController::class, 'update'])->name('factures.update');
            Route::delete('/', [FactureController::class, 'destroy'])->name('factures.destroy');
            Route::post('/emettre', [FactureController::class, 'emettre'])->name('factures.emettre');
            Route::post('/avoir', [FactureController::class, 'avoir'])->name('factures.avoir');
            Route::post('/relances', [FactureController::class, 'relancesAuto'])->name('factures.relances');
            Route::post('/lien', [FactureController::class, 'lien'])->name('factures.lien');
            Route::get('/pdf', [FactureController::class, 'pdf'])->name('factures.pdf');
        });
    });

    Route::middleware('gerant')->group(function () {
        Route::get('/journal', [JournalController::class, 'index'])->name('journal');

        Route::get('/reglages', [ReglagesController::class, 'index'])->name('reglages');
        Route::get('/reglages/textes-types', [TextesTypesController::class, 'index'])->name('reglages.textes');
        Route::post('/reglages/textes-types', [TextesTypesController::class, 'store'])->name('reglages.textes.store');
        Route::put('/reglages/textes-types/{index}', [TextesTypesController::class, 'update'])->whereNumber('index')->name('reglages.textes.update');
        Route::delete('/reglages/textes-types/{index}', [TextesTypesController::class, 'destroy'])->whereNumber('index')->name('reglages.textes.destroy');
        Route::post('/reglages/emails/test', [ReglagesController::class, 'testerEmail'])->middleware('throttle:5,1')->name('reglages.emails.test');
        Route::get('/reglages/assurance/attestation', [FichiersController::class, 'attestation'])->name('reglages.attestation');
        Route::get('/reglages/{section}', [ReglagesController::class, 'edit'])->name('reglages.edit');
        Route::put('/reglages/{section}', [ReglagesController::class, 'update'])->name('reglages.update');
        Route::get('/corbeille', [CorbeilleController::class, 'index'])->name('corbeille');
        Route::post('/corbeille/{type}/{id}/restaurer', [CorbeilleController::class, 'restaurer'])
            ->whereNumber('id')->name('corbeille.restaurer');
    });
});
