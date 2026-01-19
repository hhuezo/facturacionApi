<?php

namespace App\Models\mh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPago extends Model
{
    use HasFactory;
     protected $table = 'mh_tipo_pago';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'visible',
    ];

    protected $attributes = [
        'visible' => 'S',
    ];
}
