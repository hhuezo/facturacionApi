<?php

namespace App\Models\catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPlazo extends Model
{
    use HasFactory;

    protected $table = 'mh_tipo_plazos';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'eliminado',
    ];

    protected $attributes = [
        'eliminado' => 'N',
    ];
}
