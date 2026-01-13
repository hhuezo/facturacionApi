<?php

namespace App\Traits;

trait MhEndpointsTrait
{
    protected function obtenerEndpointsMh(int|string $ambiente): array
    {
        if ((int)$ambiente === 1) {
            return [
                'urlToken'            => 'https://apitest.dtes.mh.gob.sv/seguridad/auth',
                'urlRecepcion'        => 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte',
                'urlRecepcionLote'    => 'https://apitest.dtes.mh.gob.sv/fesv/recepcionlote/',
                'urlConsultaDTE'      => 'https://apitest.dtes.mh.gob.sv/fesv/recepcion/consultadte/',
                'urlConsultaLoteDTE'  => 'https://apitest.dtes.mh.gob.sv/fesv/recepcion/consultadtelote/{codigoLote}',
                'urlContingencia'     => 'https://apitest.dtes.mh.gob.sv/fesv/contingencia',
                'urlAnulacion'        => 'https://apitest.dtes.mh.gob.sv/fesv/anulardte',
            ];
        }

        return [
            'urlToken'            => 'https://api.dtes.mh.gob.sv/seguridad/auth',
            'urlRecepcion'        => 'https://api.dtes.mh.gob.sv/fesv/recepciondte',
            'urlRecepcionLote'    => 'https://api.dtes.mh.gob.sv/fesv/recepcionlote/',
            'urlConsultaDTE'      => 'https://api.dtes.mh.gob.sv/fesv/recepcion/consultadte/',
            'urlConsultaLoteDTE'  => 'https://api.dtes.mh.gob.sv/fesv/recepcion/consultadtelote/{codigoLote}',
            'urlContingencia'     => 'https://api.dtes.mh.gob.sv/fesv/contingencia',
            'urlAnulacion'        => 'https://api.dtes.mh.gob.sv/fesv/anulardte',
        ];
    }
}
