<?php

namespace App\Support;

// Formato compartido de los números "oficiales" del sistema (contraseña,
// no. de oficio/providencia, correlativo de recurso, etc.) — usado tanto al
// generar los .docx de Oficio/Providencia (DocumentoOficialService) como en
// los asuntos de correo (NotificacionService), para que un mismo número se
// vea igual en todos lados: "2051-2026" -> "2,051-2026" (coma de miles a
// partir de 1,000, tal como lo pidió el usuario).
class FormatoOficial
{
    public static function conComas(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (preg_match('/^(\d+)(.*)$/', trim($valor), $m)) {
            return number_format((int) $m[1]).$m[2];
        }

        return $valor;
    }
}
