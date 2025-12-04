<?php

namespace App\Models\mh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    use HasFactory;

    protected $table = 'mh_municipios';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idDepartamento',
        'codigo',
        'nombre',
        'eliminado'
    ];

    protected $casts = [
        'idDepartamento' => 'integer',
        'eliminado' => 'string'
    ];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'idDepartamento');
    }
}
