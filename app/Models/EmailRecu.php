<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailRecu extends Model
{
    protected $table = 'emails_recus';

    protected $fillable = ['message_id', 'expediteur', 'expediteur_email', 'sujet', 'extrait', 'est_demande', 'recu_at'];

    protected function casts(): array
    {
        return ['recu_at' => 'datetime', 'est_demande' => 'boolean'];
    }
}
