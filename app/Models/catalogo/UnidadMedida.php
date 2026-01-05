<?php

namespace App\Models\catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    use HasFactory;

    protected $table = 'mh_unidad_medida';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'eliminado',
        'visible',
    ];

    protected $attributes = [
        'eliminado' => 'N',
        'visible'   => 'S',
    ];
}
