<?php

namespace App\Support;

/**
 * Messages prêts à envoyer par WhatsApp, SMS ou à copier.
 */
class MessagesPrets
{
    public static function whatsapp(?string $telephone, string $message): string
    {
        $numero = Telephone::normaliser($telephone);
        if ($numero && strlen($numero) === 10 && $numero[0] === '0') {
            $numero = '33'.substr($numero, 1);
        }

        return 'https://wa.me/'.($numero ?? '').'?text='.rawurlencode($message);
    }

    public static function sms(?string $telephone, string $message): string
    {
        return 'sms:'.Telephone::normaliser($telephone).'?&body='.rawurlencode($message);
    }

    /**
     * Texte court (sans formules longues) à partir du corps d'un modèle d'email.
     */
    public static function court(string $corps): string
    {
        $texte = preg_replace("/\n{3,}/", "\n\n", trim($corps)) ?? $corps;

        return mb_strimwidth($texte, 0, 900, '…');
    }
}
