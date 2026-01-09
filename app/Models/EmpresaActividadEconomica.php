<?php

namespace App\Models;

use App\Models\mh\ActividadEconomica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaActividadEconomica extends Model
{
    use HasFactory;

    protected $table = 'general_datos_empresa_actividades_economicas';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idEmpresa',
        'idActividad',
        'actividadPrincipal',
        'eliminado',
        'idUsuarioRegistra',
        'fechaRegistro',
        'idUsuarioElimina',
        'fechaElimina',
    ];

    protected $casts = [
        'fechaRegistro' => 'datetime',
        'fechaElimina'  => 'datetime',
    ];

    /* =========================
     * RELACIONES
     * ========================= */

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'idEmpresa');
    }

    public function actividadEconomica()
    {
        return $this->belongsTo(ActividadEconomica::class, 'idActividad');
    }

    public function usuarioRegistra()
    {
        return $this->belongsTo(Usuario::class, 'idUsuarioRegistra');
    }

    public function usuarioElimina()
    {
        return $this->belongsTo(Usuario::class, 'idUsuarioElimina');
    }
}
