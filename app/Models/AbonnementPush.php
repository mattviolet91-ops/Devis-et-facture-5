<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbonnementPush extends Model
{
    protected $table = 'abonnements_push';

    protected $fillable = ['user_id', 'endpoint', 'cle_p256dh', 'cle_auth', 'appareil'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
