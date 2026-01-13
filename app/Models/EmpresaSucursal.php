<?php

namespace App\Models;

use App\Models\mh\Departamento;
use App\Models\mh\Municipio;
use App\Models\mh\TipoEstablecimiento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaSucursal extends Model
{
    use HasFactory;

    protected $table = 'general_datos_empresa_sucursales';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false; // no usa created_at / updated_at

    protected $fillable = [
        'idEmpresa',
        'idTipoEstablecimiento',
        'responsable',
        'telefono',
        'correo',
        'idDepartamento',
        'idMunicipio',
        'direccion',
        'eliminado',
        'idUsuarioRegistra',
        'fechaRegistro',
        'idUsuarioElimina',
        'fechaElimina',
        'nombreSucursal',
        'codigoEstablecimiento',
    ];

    protected $casts = [
        'fechaRegistro' => 'datetime',
        'fechaElimina'  => 'datetime',
    ];


    public function tipoEstablecimiento()
    {
        return $this->belongsTo(TipoEstablecimiento::class, 'idTipoEstablecimiento', 'id');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'idDepartamento', 'id');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'idMunicipio', 'id');
    }
}
