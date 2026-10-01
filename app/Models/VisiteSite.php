<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisiteSite extends Model
{
    public const UPDATED_AT = null;

    public const EVENEMENTS = [
        'vue' => 'Page vue',
        'appeler' => 'Appeler',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'devis' => 'Demander un devis',
        'formulaire' => 'Formulaire envoyé',
    ];

    /** Durée de conservation (mois). */
    public const CONSERVATION_MOIS = 13;

    protected $table = 'visites_site';

    protected $fillable = ['jour', 'empreinte', 'evenement', 'page', 'source', 'appareil'];
}
