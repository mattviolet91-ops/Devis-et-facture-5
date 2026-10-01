<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailDeTest extends Mailable
{
    use Queueable;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Email de test : l\'envoi fonctionne');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.test');
    }
}
