<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asunto' => 'sometimes|string|max:200',
            'descripcion' => 'sometimes|nullable|string',
            'prioridad' => 'sometimes|in:alta,media,baja',
            'estatus' => 'sometimes|in:abierto,en_proceso,resuelto,cerrado',
            'fecha_limite' => 'sometimes|nullable|date',
            'sla_horas' => 'sometimes|nullable|integer|min:1',
            'fecha_atencion' => 'sometimes|nullable|date',
            'usuario_asignado_id' => 'sometimes|nullable|integer',
            'area_id' => 'sometimes|nullable|integer',
            'equipo_id' => 'sometimes|nullable|integer',
        ];
    }
}
