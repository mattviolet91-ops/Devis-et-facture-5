<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteClient extends Model
{
    protected $table = 'notes_clients';

    protected $fillable = ['client_id', 'user_id', 'texte'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
