<?php

namespace App\Services;

use App\Mail\EmailDocument;
use App\Models\Client;
use App\Models\Devis;
use App\Models\EmailEnvoye;
use App\Models\Facture;
use App\Models\LienClient;
use App\Models\Rapport;
use App\Models\RendezVous;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Rédaction et envoi des emails aux clients : modèles avec variables, PDF joint,
 * copie cachée à l'entreprise, historique.
 */
class EnvoiEmail
{
    public const VARIABLES = ['{salutation}', '{client}', '{numero}', '{montant}', '{reste}', '{echeance}', '{lien}', '{entreprise}', '{telephone}'];

    public function __construct(private ConfigurationEmail $configuration) {}

    /**
     * Valeurs des variables pour un document.
     *
     * @return array<string, string>
     */
    public function variables(Model $document): array
    {
        /** @var Client $client */
        $client = $document->client;
        $lien = in_array($document->statut ?? '', ['brouillon'], true) ? '' : LienClient::pour($document)->url();

        return [
            '{salutation}' => self::salutation($client),
            '{client}' => $client->nomComplet(),
            '{numero}' => (string) ($document->numero ?? ''),
            '{montant}' => Montant::formater((int) $document->total_ttc),
            '{reste}' => $document instanceof Facture ? Montant::formater($document->resteAPayer()) : Montant::formater((int) $document->total_ttc),
            '{echeance}' => match (true) {
                $document instanceof Facture => (string) $document->date_echeance?->format('d/m/Y'),
                $document instanceof Devis => (string) $document->dateValidite()?->format('d/m/Y'),
                default => '',
            },
            '{lien}' => $lien,
            '{entreprise}' => (string) reglage('identite.nom_commercial'),
            '{telephone}' => (string) reglage('identite.telephone'),
        ];
    }

    /**
     * Variables du rappel de rendez-vous envoyé au client.
     *
     * @return array<string, string>
     */
    public function variablesRendezVous(RendezVous $rdv): array
    {
        return [
            '{salutation}' => self::salutation($rdv->client),
            '{client}' => $rdv->client->nomComplet(),
            '{date}' => $rdv->debut->translatedFormat('l j F'),
            '{horaire}' => $rdv->horaire(),
            '{objet}' => $rdv->titre,
            '{adresse}' => (string) $rdv->adresse(),
            '{entreprise}' => (string) reglage('identite.nom_commercial'),
            '{telephone}' => (string) reglage('identite.telephone'),
        ];
    }

    public static function salutation(Client $client): string
    {
        if ($client->estProfessionnel() && ! $client->nom) {
            return 'Bonjour';
        }

        return match ($client->civilite) {
            'M.' => 'Bonjour Monsieur '.$client->nom,
            'Mme' => 'Bonjour Madame '.$client->nom,
            'M. et Mme' => 'Bonjour Madame, Monsieur',
            default => 'Bonjour '.trim($client->prenom.' '.$client->nom),
        };
    }

    /**
     * @return array{sujet: string, corps: string}
     */
    public function rediger(Model $document, string $modele): array
    {
        $variables = $this->variables($document);

        return [
            'sujet' => strtr((string) reglage("emails.modeles.{$modele}.sujet"), $variables),
            'corps' => strtr((string) reglage("emails.modeles.{$modele}.corps"), $variables),
        ];
    }

    /**
     * Envoie l'email (avec le PDF du document) et l'enregistre dans l'historique.
     */
    public function envoyer(Model $document, string $destinataire, string $sujet, string $corps, ?string $modele, bool $joindrePdf, ?int $userId, bool $automatique = false): EmailEnvoye
    {
        [$pdf, $nomPdf] = $joindrePdf ? $this->pdf($document) : [null, null];
        $copie = reglage('emails.copie_cachee') && reglage('emails.adresse') ? [(string) reglage('emails.adresse')] : [];

        $historique = EmailEnvoye::create([
            'document_type' => $document->getMorphClass(),
            'document_id' => $document->getKey(),
            'client_id' => $document->client_id,
            'destinataire' => $destinataire,
            'sujet' => $sujet,
            'corps' => $corps,
            'piece_jointe' => $nomPdf,
            'modele' => $modele,
            'automatique' => $automatique,
            'statut' => 'envoye',
            'user_id' => $userId,
        ]);

        try {
            $this->configuration->appliquer();
            Mail::to($destinataire)->send(new EmailDocument($sujet, $corps, $pdf, $nomPdf, $copie));
        } catch (\Throwable $e) {
            Log::warning('Email non envoyé : '.$e->getMessage());
            $historique->update(['statut' => 'erreur', 'erreur' => mb_substr($e->getMessage(), 0, 490)]);
        }

        return $historique;
    }

    /**
     * @return array{?string, ?string}
     */
    private function pdf(Model $document): array
    {
        return match (true) {
            $document instanceof Devis => [app(PdfDevis::class)->contenu($document), 'devis-'.str_replace(' ', '-', $document->reference()).'.pdf'],
            $document instanceof Rapport => [app(PdfRapport::class)->generer($document), 'rapport-intervention-'.$document->date_intervention->format('Y-m-d').'.pdf'],
            $document instanceof Facture => [app(PdfFacture::class)->contenu($document), mb_strtolower(str_replace([' ', "'"], '-', $document->libelleType())).'-'.str_replace(' ', '-', $document->reference()).'.pdf'],
            default => [null, null],
        };
    }
}
