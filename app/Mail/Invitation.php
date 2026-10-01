<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invitation à rejoindre l'application (lien valable 7 jours).
 */
class Invitation extends Mailable
{
    use Queueable;

    public function __construct(public string $lien, public string $role) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitation : '.((string) reglage('identite.nom_commercial') ?: 'application de gestion'));
    }

    public function content(): Content
    {
        $corps = "Bonjour,\n\nVous êtes invité à utiliser l'application de "
            .((string) reglage('identite.nom_commercial') ?: 'l\'entreprise').' (compte '.mb_strtolower($this->role).").\n\n"
            ."Pour choisir votre mot de passe, ouvrez ce lien (valable 7 jours) :\n".$this->lien
            ."\n\nSi vous n'attendiez pas cette invitation, ignorez ce message.";

        return new Content(text: 'emails.document-texte', html: 'emails.document', with: ['corps' => $corps]);
    }
}
