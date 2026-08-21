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

    // País del solicitante (Fase 22b, a pedido del usuario: "agrega pais").
    // 'Guatemala' va primero porque es, por lejos, el valor más común y es
    // el que el controlador usaba fijo antes de este campo existir — así
    // el formulario sigue mostrando "Guatemala" primero por defecto.
    public const PAISES = [
        'Guatemala',
        'Belice', 'Costa Rica', 'El Salvador', 'Honduras', 'Nicaragua', 'Panamá',
        'México',
        'Argentina', 'Bolivia', 'Brasil', 'Chile', 'Colombia', 'Cuba', 'Ecuador',
        'Estados Unidos', 'Paraguay', 'Perú', 'Puerto Rico', 'República Dominicana',
        'Uruguay', 'Venezuela',
        'Canadá', 'España',
        'Alemania', 'Francia', 'Italia', 'Reino Unido',
        'Otro',
    ];
}
