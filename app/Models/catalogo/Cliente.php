<?php

namespace App\Models\catalogo;

use App\Models\mh\ActividadEconomica;
use App\Models\mh\Departamento;
use App\Models\mh\Municipio;
use App\Models\mh\Pais;
use App\Models\mh\TipoDocumentoIdentidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes_datos_generales';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idEmpresa',
        'nombreCliente',
        'codigoCliente',
        'idTipoDocumentoIdentidad',
        'numeroDocumento',
        'nrc',
        'idActividadEconomica',
        'clienteFrecuente',
        'esExento',
        'idTipoPersona',
        'idTipoContribuyente',
        'correo',
        'telefono',
        'idPais',
        'idDepartamento',
        'idMunicipio',
        'direccion',
        'idUsuarioRegistra',
        'fechaRegistro',
        'idUsuarioActualiza',
        'fechaActualiza',
        'eliminado',
        'fechaElimina',
        'idUsuarioElimina'
    ];

    protected $casts = [
        'idEmpresa' => 'integer',
        'idTipoDocumentoIdentidad' => 'integer',
        'idActividadEconomica' => 'integer',
        'clienteFrecuente' => 'string',
        'esExento' => 'string',
        'idTipoPersona' => 'integer',
        'idTipoContribuyente' => 'integer',
        'idPais' => 'integer',
        'idDepartamento' => 'integer',
        'idMunicipio' => 'integer',
        'idUsuarioRegistra' => 'integer',
        'idUsuarioActualiza' => 'integer',
        'idUsuarioElimina' => 'integer',

        'fechaRegistro' => 'datetime',
        'fechaActualiza' => 'datetime',
        'fechaElimina' => 'datetime',
    ];


    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumentoIdentidad::class, 'idTipoDocumentoIdentidad');
    }

    public function actividadEconomica()
    {
        return $this->belongsTo(ActividadEconomica::class, 'idActividadEconomica','id');
    }

    public function tipoPersona()
    {
        return $this->belongsTo(TipoPersona::class, 'idTipoPersona');
    }

    public function tipoContribuyente()
    {
        return $this->belongsTo(TipoContribuyente::class, 'idTipoContribuyente');
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'idPais');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'idDepartamento');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'idMunicipio');
    }
}
