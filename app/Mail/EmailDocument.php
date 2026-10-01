<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email à un client (texte rédigé à partir d'un modèle), avec le PDF en pièce jointe.
 */
class EmailDocument extends Mailable
{
    use Queueable;

    /**
     * @param  list<string>  $copieCachee
     */
    public function __construct(
        public string $sujet,
        public string $corps,
        public ?string $pdf = null,
        public ?string $nomPdf = null,
        public array $copieCachee = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sujet,
            bcc: array_map(fn (string $e) => new Address($e), $this->copieCachee),
            replyTo: reglage('identite.email') ? [new Address((string) reglage('identite.email'), (string) reglage('identite.nom_commercial'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.document-texte', html: 'emails.document', with: ['corps' => $this->corps]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->pdf ? [Attachment::fromData(fn () => $this->pdf, $this->nomPdf ?? 'document.pdf')->withMime('application/pdf')] : [];
    }
}
