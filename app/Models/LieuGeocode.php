<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LieuGeocode extends Model
{
    protected $table = 'lieux_geocodes';

    protected $fillable = ['recherche', 'latitude', 'longitude', 'libelle'];
}
