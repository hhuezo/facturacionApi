<?php

namespace App\Models;

use App\Models\mh\Ambiente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaConfigTransmisionDte extends Model
{
    use HasFactory;

    protected $table = 'general_datos_empresa_config_transmision_dte';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idEmpresa',
        'idTipoAmbiente',
        'clavePublica',
        'clavePrivada',
        'passwordApi',
        'urlFirmador',
        'enviaCorreos',
        'host',
        'puerto',
        'usuarioAPI',
        'claveSMTP',
        'correoEnviador',
        'idUsuarioRegistra',
        'idUsuarioActualiza',
        'fechaRegistro',
        'fechaElimina',
    ];

    protected $casts = [
        'fechaRegistro' => 'datetime',
        'fechaElimina'  => 'datetime',
        'enviaCorreos'  => 'string',
    ];

    /* =============================
     * Relaciones
     * ============================= */

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'idEmpresa', 'id');
    }

    public function ambiente()
    {
        return $this->belongsTo(
            Ambiente::class,
            'idTipoAmbiente',
            'id'
        );
    }

    public function usuarioRegistra()
    {
        return $this->belongsTo(User::class, 'idUsuarioRegistra', 'id');
    }

    public function usuarioActualiza()
    {
        return $this->belongsTo(User::class, 'idUsuarioActualiza', 'id');
    }
}
