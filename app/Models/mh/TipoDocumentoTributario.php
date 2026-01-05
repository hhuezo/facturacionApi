<?php

namespace App\Models\mh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoDocumentoTributario extends Model
{
    use HasFactory;

     protected $table = 'mh_tipo_documento_tributario';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;
    protected $fillable = [
        'codigo',
        'nombre',
    ];
}
