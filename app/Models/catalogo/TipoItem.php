<?php

namespace App\Models\catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoItem extends Model
{
    use HasFactory;

    protected $table = 'mh_tipo_item';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'eliminado'
    ];

    protected $casts = [
        'eliminado' => 'string',
    ];
}
