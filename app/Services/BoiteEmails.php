<?php

namespace App\Services;

use App\Support\Reglages;
use Illuminate\Support\Carbon;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Lecture SEULE de la boîte de réception (IMAP Gmail) : rien n'est supprimé ni marqué comme lu.
 */
class BoiteEmails
{
    public function __construct(private Reglages $reglages) {}

    public function estConfiguree(): bool
    {
        return (bool) reglage('suivi.imap_actif') && app(ConfigurationEmail::class)->estConfiguree();
    }

    /**
     * Derniers emails reçus.
     *
     * @return list<array{message_id: string, expediteur: ?string, expediteur_email: ?string, sujet: string, texte: string, date: Carbon}>
     */
    public function derniers(int $jours = 7, int $maximum = 40): array
    {
        $client = (new ClientManager)->make([
            'host' => (string) reglage('suivi.imap_serveur'),
            'port' => (int) reglage('suivi.imap_port'),
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => (string) reglage('emails.adresse'),
            'password' => (string) $this->reglages->getSecret('emails.mot_de_passe'),
            'protocol' => 'imap',
            'timeout' => 20,
        ]);
        $client->connect();

        try {
            $messages = $client->getFolder('INBOX')->query()
                ->whereSince(now()->subDays($jours))
                ->leaveUnread()
                ->setFetchOrderDesc()
                ->limit($maximum)
                ->get();

            $resultat = [];
            /** @var Message $message */
            foreach ($messages as $message) {
                $de = $message->getFrom()->first();
                $texte = $message->hasTextBody() ? $message->getTextBody() : strip_tags((string) $message->getHTMLBody());
                $resultat[] = [
                    'message_id' => (string) ($message->getMessageId()->first() ?: sha1($message->getSubject().$message->getDate())),
                    'expediteur' => $de?->personal ?: null,
                    'expediteur_email' => $de?->mail ?: null,
                    'sujet' => (string) $message->getSubject(),
                    'texte' => (string) $texte,
                    'date' => Carbon::parse((string) $message->getDate()->first()),
                ];
            }

            return $resultat;
        } finally {
            $client->disconnect();
        }
    }
}
