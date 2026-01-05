<?php

namespace App\Models\mh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CondicionVenta extends Model
{
    use HasFactory;

    protected $table = 'mh_condicion_venta';

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
