<?php

namespace App\Helpers;

class NumeroALetras
{
    private $UNIDADES = [
        '',
        'UN ',
        'DOS ',
        'TRES ',
        'CUATRO ',
        'CINCO ',
        'SEIS ',
        'SIETE ',
        'OCHO ',
        'NUEVE ',
        'DIEZ ',
        'ONCE ',
        'DOCE ',
        'TRECE ',
        'CATORCE ',
        'QUINCE ',
        'DIECISEIS ',
        'DIECISIETE ',
        'DIECIOCHO ',
        'DIECINUEVE ',
        'VEINTE '
    ];

    private $DECENAS = [
        'VEINTI',
        'TREINTA ',
        'CUARENTA ',
        'CINCUENTA ',
        'SESENTA ',
        'SETENTA ',
        'OCHENTA ',
        'NOVENTA ',
        'CIEN '
    ];

    private $CENTENAS = [
        'CIENTO ',
        'DOSCIENTOS ',
        'TRESCIENTOS ',
        'CUATROCIENTOS ',
        'QUINIENTOS ',
        'SEISCIENTOS ',
        'SETECIENTOS ',
        'OCHOCIENTOS ',
        'NOVECIENTOS '
    ];

    public function toInvoice($number, $decimals = 2, $currency = 'CENTAVOS')
    {
        $number = number_format($number, $decimals, '.', '');
        $splitNumber = explode('.', $number);
        $entero = $splitNumber[0];
        $decimal = isset($splitNumber[1]) ? $splitNumber[1] : '00';

        $converted = $this->convertNumberToLetter($entero);
        $converted .= 'DÓLARES CON ' . $this->convertNumberToLetter($decimal) . $currency;

        return trim($converted);
    }

    private function convertNumberToLetter($number)
    {
        $number = trim($number);
        if ($number == 0) {
            return 'CERO ';
        }

        $number = str_pad($number, 6, '0', STR_PAD_LEFT);
        $millones = substr($number, 0, 3);
        $miles = substr($number, 3, 3);

        $result = '';

        if (intval($millones) > 0) {
            if ($millones == '001') {
                $result .= 'UN MILLON ';
            } else if (intval($millones) > 0) {
                $result .= $this->convertGroup($millones) . 'MILLONES ';
            }
        }

        if (intval($miles) > 0) {
            if ($miles == '001') {
                $result .= 'MIL ';
            } else if (intval($miles) > 0) {
                $result .= $this->convertGroup($miles) . 'MIL ';
            }
        }

        if (intval($millones) == 0 && intval($miles) == 0) {
            $result .= $this->convertGroup(substr($number, -3));
        } else {
            $result .= $this->convertGroup(substr($number, -3));
        }

        return $result;
    }

    private function convertGroup($number)
    {
        $output = '';

        if ($number == '100') {
            return 'CIEN ';
        }

        $number = str_pad($number, 3, '0', STR_PAD_LEFT);
        $centenas = intval(substr($number, 0, 1));
        $decenas = intval(substr($number, 1, 1));
        $unidades = intval(substr($number, 2, 1));

        if ($centenas > 0) {
            if ($centenas == 1) {
                $output .= 'CIENTO ';
            } else {
                $output .= $this->CENTENAS[$centenas - 1];
            }
        }

        if ($decenas > 2) {
            $output .= $this->DECENAS[$decenas - 2];
            if ($unidades > 0) {
                $output .= 'Y ' . $this->UNIDADES[$unidades];
            }
        } else if ($decenas == 2) {
            if ($unidades > 0) {
                $output .= $this->DECENAS[0] . $this->UNIDADES[$unidades];
            } else {
                $output .= 'VEINTE ';
            }
        } else if ($decenas == 1) {
            $output .= $this->UNIDADES[10 + $unidades];
        } else if ($unidades > 0) {
            $output .= $this->UNIDADES[$unidades];
        }

        return $output;
    }
}
