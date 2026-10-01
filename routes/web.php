<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use App\Http\Controllers\Auth\NouveauMotDePasseController;
use App\Http\Controllers\ChantierController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompteurSiteController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\CorbeilleController;
use App\Http\Controllers\DemandePubliqueController;
use App\Http\Controllers\DevisController;
use App\Http\Controllers\DevisExpressController;
use App\Http\Controllers\EnvoiController;
use App\Http\Controllers\EspaceClientController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\FichiersController;
use App\Http\Controllers\FraisController;
use App\Http\Controllers\ImportClientsController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\NoteClientController;
use App\Http\Controllers\NotificationsTelephoneController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\PaiementEnLigneController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PieceJointeController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PrestationController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\ReglagesController;
use App\Http\Controllers\SignatureSurPlaceController;
use App\Http\Controllers\StatistiquesController;
use App\Http\Controllers\StatistiquesSiteController;
use App\Http\Controllers\SuiviController;
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

    // Compteur du site internet de l'entreprise (balise à coller sur le site).
    Route::get('/s.js', [CompteurSiteController::class, 'script'])->name('compteur.script');
    Route::post('/s', [CompteurSiteController::class, 'collecter'])->middleware('throttle:120,1')->name('compteur.collecter');
});

// Pages clients (lien personnel, sans compte), aussi sur l'adresse réservée aux clients.
Route::prefix('/c/{jeton}')->middleware('throttle:60,1')->name('client.')->group(function () {
    Route::get('/', [EspaceClientController::class, 'show'])->name('document');
    Route::get('/pdf', [EspaceClientController::class, 'pdf'])->name('pdf');
    Route::get('/photo/{photo}', [EspaceClientController::class, 'photo'])->whereNumber('photo')->name('photo');
    Route::post('/signer', [EspaceClientController::class, 'signer'])->middleware('throttle:10,1')->name('signer');
    Route::post('/refuser', [EspaceClientController::class, 'refuser'])->middleware('throttle:10,1')->name('refuser');
    Route::post('/modification', [EspaceClientController::class, 'modification'])->middleware('throttle:10,1')->name('modification');
    Route::post('/payer', [PaiementEnLigneController::class, 'payer'])->middleware('throttle:10,1')->name('payer');
    Route::get('/paiement/merci', [PaiementEnLigneController::class, 'merci'])->name('paiement.merci');
    Route::match(['get', 'post'], '/paiement/annule', [PaiementEnLigneController::class, 'annule'])
        ->withoutMiddleware(ValidateCsrfToken::class)->name('paiement.annule');
});

// Notification de myPOS (serveur à serveur) : pas de session ni de jeton CSRF, signature vérifiée.
Route::post('/paiement/mypos/notification', [PaiementEnLigneController::class, 'notification'])
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        ValidateCsrfToken::class,
        CompteActif::class,
        ConfigurationRequise::class,
    ])
    ->middleware('throttle:60,1')->name('mypos.notification');

// Formulaire public « Demander un devis » (lien à mettre sur le site de l'entreprise).
Route::get('/demande', [DemandePubliqueController::class, 'create'])->name('demande.create');
Route::post('/demande', [DemandePubliqueController::class, 'store'])->middleware('throttle:5,10')->name('demande.store');
Route::get('/demande/merci', [DemandePubliqueController::class, 'merci'])->name('demande.merci');

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
    Route::get('/accueil/personnaliser', [AccueilController::class, 'personnaliser'])->name('accueil.personnaliser');
    Route::post('/accueil/personnaliser', [AccueilController::class, 'enregistrer'])->name('accueil.enregistrer');
    Route::post('/alertes/{id}/lue', [AccueilController::class, 'lireAlerte'])->name('alertes.lue');
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('logout');

    Route::get('/plus', [PagesController::class, 'plus'])->name('plus');
    Route::get('/visionneuse', [VisionneuseController::class, 'show'])->name('visionneuse');
    Route::get('/nouveau', [PagesController::class, 'nouveau'])->name('nouveau');

    // Notifications sur le téléphone.
    Route::post('/notifications/abonnement', [NotificationsTelephoneController::class, 'abonner'])->middleware('throttle:10,1')->name('push.abonner');
    Route::delete('/notifications/abonnement', [NotificationsTelephoneController::class, 'desabonner'])->name('push.desabonner');
    Route::post('/notifications/essai', [NotificationsTelephoneController::class, 'essai'])->middleware('throttle:5,1')->name('push.essai');

    // Planning (gérant et commercial).
    Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
    Route::get('/planning/a-planifier', [PlanningController::class, 'aPlanifier'])->name('planning.a-planifier');
    Route::get('/planning/export.ics', [PlanningController::class, 'exporter'])->name('planning.exporter');
    Route::get('/planning/nouveau', [PlanningController::class, 'create'])->name('planning.create');
    Route::post('/planning', [PlanningController::class, 'store'])->name('planning.store');
    Route::prefix('/planning/{rdv}')->whereNumber('rdv')->group(function () {
        Route::get('/', [PlanningController::class, 'show'])->name('planning.show');
        Route::get('/modifier', [PlanningController::class, 'edit'])->name('planning.edit');
        Route::put('/', [PlanningController::class, 'update'])->name('planning.update');
        Route::delete('/', [PlanningController::class, 'destroy'])->name('planning.destroy');
        Route::post('/fait', [PlanningController::class, 'fait'])->name('planning.fait');
        Route::post('/provenance', [PlanningController::class, 'provenance'])->name('planning.provenance');
        Route::get('/calendrier.ics', [PlanningController::class, 'ics'])->name('planning.ics');
    });

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

    // Envoi par email (devis : tous ; factures : gérant).
    Route::get('/envoyer/{type}/{id}', [EnvoiController::class, 'create'])->whereIn('type', ['devis', 'facture', 'rapport'])->whereNumber('id')->name('envoi.create');
    Route::post('/envoyer/{type}/{id}', [EnvoiController::class, 'store'])->whereIn('type', ['devis', 'facture', 'rapport'])->whereNumber('id')->middleware('throttle:20,1')->name('envoi.store');

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

    // Suivi commercial.
    Route::get('/suivi', [SuiviController::class, 'index'])->name('suivi');
    Route::get('/suivi/demandes/{demande}', [SuiviController::class, 'demande'])->whereNumber('demande')->name('suivi.demande');
    Route::post('/suivi/demandes/{demande}/statut', [SuiviController::class, 'statutDemande'])->whereNumber('demande')->name('suivi.demande.statut');
    Route::get('/suivi/demandes/{demande}/photo/{index}', [SuiviController::class, 'photoDemande'])->whereNumber(['demande', 'index'])->name('suivi.demande.photo');
    Route::post('/suivi/demandes/{demande}/client', [SuiviController::class, 'creerClient'])->whereNumber('demande')->name('suivi.demande.client');
    Route::post('/suivi/avis/{client}', [SuiviController::class, 'avis'])->whereNumber('client')->middleware('throttle:20,1')->name('suivi.avis');
    Route::post('/suivi/entretien/{client}', [SuiviController::class, 'entretien'])->whereNumber('client')->middleware('throttle:20,1')->name('suivi.entretien');
    Route::middleware('gerant')->group(function () {
        Route::get('/suivi/emails', [SuiviController::class, 'emails'])->name('suivi.emails');
        Route::post('/suivi/emails', [SuiviController::class, 'actualiser'])->middleware('throttle:4,1')->name('suivi.emails.actualiser');
        Route::post('/suivi/emails/verifier', [SuiviController::class, 'verifier'])->middleware('throttle:4,1')->name('suivi.emails.verifier');
    });

    // Photos de chantier et rapports d'intervention.
    Route::get('/clients/{client}/photos', [PhotoController::class, 'index'])->whereNumber('client')->name('photos.index');
    Route::post('/clients/{client}/photos', [PhotoController::class, 'store'])->whereNumber('client')->middleware('throttle:30,1')->name('photos.store');
    Route::prefix('/photos/{photo}')->whereNumber('photo')->group(function () {
        Route::get('/', [PhotoController::class, 'show'])->name('photos.show');
        Route::get('/miniature', [PhotoController::class, 'miniature'])->name('photos.miniature');
        Route::get('/annoter', [PhotoController::class, 'annotation'])->name('photos.annotation');
        Route::post('/annoter', [PhotoController::class, 'annoter'])->middleware('throttle:20,1')->name('photos.annoter');
        Route::post('/retablir', [PhotoController::class, 'retablir'])->name('photos.retablir');
        Route::put('/', [PhotoController::class, 'update'])->name('photos.update');
        Route::delete('/', [PhotoController::class, 'destroy'])->name('photos.destroy');
    });
    Route::get('/rapports/nouveau', [RapportController::class, 'create'])->name('rapports.create');
    Route::post('/rapports', [RapportController::class, 'store'])->name('rapports.store');
    Route::prefix('/rapports/{rapport}')->whereNumber('rapport')->group(function () {
        Route::get('/', [RapportController::class, 'show'])->name('rapports.show');
        Route::get('/modifier', [RapportController::class, 'edit'])->name('rapports.edit');
        Route::put('/', [RapportController::class, 'update'])->name('rapports.update');
        Route::delete('/', [RapportController::class, 'destroy'])->name('rapports.destroy');
        Route::get('/pdf', [RapportController::class, 'pdf'])->name('rapports.pdf');
    });

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
        Route::get('/frais/{frais}/ticket', [FraisController::class, 'ticket'])->whereNumber('frais')->name('frais.ticket');
        Route::delete('/frais/{frais}', [FraisController::class, 'destroy'])->whereNumber('frais')->name('frais.destroy');
        Route::get('/statistiques', [StatistiquesController::class, 'index'])->name('statistiques');
        Route::get('/statistiques/site', [StatistiquesSiteController::class, 'index'])->name('statistiques.site');
        Route::post('/statistiques/site/jetpack', [StatistiquesSiteController::class, 'connecter'])->name('statistiques.jetpack.connecter');
        Route::get('/statistiques/site/jetpack/retour', [StatistiquesSiteController::class, 'retour'])->name('statistiques.jetpack.retour');
        Route::post('/statistiques/site/jetpack/deconnecter', [StatistiquesSiteController::class, 'deconnecter'])->name('statistiques.jetpack.deconnecter');
        Route::post('/paiements/{paiement}/annuler', [PaiementController::class, 'annuler'])->whereNumber('paiement')->name('paiements.annuler');
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
            Route::post('/paiements', [PaiementController::class, 'store'])->name('paiements.store');
            Route::post('/frais', [FraisController::class, 'store'])->middleware('throttle:30,1')->name('frais.store');
            Route::post('/paiement-essai', [PaiementEnLigneController::class, 'essai'])->name('paiements.essai');
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
