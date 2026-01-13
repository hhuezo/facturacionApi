<?php

namespace App\Models\mh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoEstablecimiento extends Model
{
    use HasFactory;


     protected $table = 'mh_tipo_establecimiento';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;
    protected $fillable = [
        'codigo',
        'nombre',
        'eliminado',
    ];
}
