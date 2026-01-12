<?php

namespace App\Models;

use App\Models\mh\TipoDocumentoTributario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaTipoDocumentoTributario extends Model
{
    use HasFactory;

    protected $table = 'general_datos_empresa_config_documentos_tributarios';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idEmpresa',
        'idTipoDocumentoTributario',
        'estado',
        'eliminado',
        'idUsuarioRegistra',
        'fechaRegistro',
        'idUsuarioElimina',
        'fechaElimina',
    ];

    protected $casts = [
        'idEmpresa' => 'integer',
        'idTipoDocumentoTributario' => 'integer',
        'idUsuarioRegistra' => 'integer',
        'idUsuarioElimina' => 'integer',
        'fechaRegistro' => 'datetime',
        'fechaElimina' => 'datetime',
    ];

    // Relaciones (ajusta namespaces/clases si tus modelos están en otra carpeta)
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'idEmpresa', 'id');
    }

    public function tipoDocumentoTributario()
    {
        return $this->belongsTo(TipoDocumentoTributario::class, 'idTipoDocumentoTributario', 'id');
    }

    public function usuarioRegistra()
    {
        return $this->belongsTo(User::class, 'idUsuarioRegistra', 'id');
    }

    public function usuarioElimina()
    {
        return $this->belongsTo(User::class, 'idUsuarioElimina', 'id');
    }

    // Scopes útiles
    public function scopeActivos($query)
    {
        return $query->where('estado', 'A')->where('eliminado', 'N');
    }

    public function scopeDeEmpresa($query, int $idEmpresa)
    {
        return $query->where('idEmpresa', $idEmpresa);
    }
}
