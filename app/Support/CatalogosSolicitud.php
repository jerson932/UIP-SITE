<?php

namespace App\Support;

/**
 * Listas fijas para los campos demográficos del solicitante (spec: "datos
 * demograficos del formulario real" en el modelo Solicitante). Se
 * comparten entre el formulario público (SolicitudPublicaController) y el
 * de registro interno (Admin\SolicitudController) para que ambos capturen
 * los mismos valores de forma consistente.
 */
class CatalogosSolicitud
{
    public const DEPARTAMENTOS = [
        'Guatemala', 'Alta Verapaz', 'Baja Verapaz', 'Chimaltenango', 'Chiquimula',
        'El Progreso', 'Escuintla', 'Huehuetenango', 'Izabal', 'Jalapa', 'Jutiapa',
        'Petén', 'Quetzaltenango', 'Quiché', 'Retalhuleu', 'Sacatepéquez',
        'San Marcos', 'Santa Rosa', 'Sololá', 'Suchitepéquez', 'Totonicapán', 'Zacapa',
    ];

    public const GENEROS = ['Femenino', 'Masculino', 'Otro', 'Prefiero no decir'];

    public const RANGOS_EDAD = ['18-25', '26-35', '36-45', '46-60', '60+'];

    public const MEDIOS_RECEPCION = [
        'electronica' => 'Electrónica',
        'fisica' => 'Física',
        'correo' => 'Correo',
    ];
}
