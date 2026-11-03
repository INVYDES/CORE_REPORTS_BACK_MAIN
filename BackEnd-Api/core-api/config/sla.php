<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SLA de tickets por prioridad
    |--------------------------------------------------------------------------
    |
    | Horas objetivo de atencion contadas desde la fecha de solicitud. Se usan
    | cuando el ticket no trae `sla_horas` explicito y, si tampoco trae
    | `fecha_limite`, esta se calcula automaticamente a partir de ellas.
    |
    */

    'ticket' => [
        'alta' => (int) env('SLA_TICKET_ALTA_HORAS', 4),
        'media' => (int) env('SLA_TICKET_MEDIA_HORAS', 24),
        'baja' => (int) env('SLA_TICKET_BAJA_HORAS', 72),
    ],

];
