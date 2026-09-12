<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización de rol la aplica TicketPolicy en el controlador
    }

    public function rules(): array
    {
        return [
            'folio' => 'sometimes|string|max:50',
            'asunto' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'solicitante' => 'nullable|string|max:150',
            'cargo' => 'nullable|string|max:150',
            'prioridad' => 'sometimes|in:alta,media,baja',
            'estatus' => 'sometimes|in:abierto,en_proceso,resuelto,cerrado',
            'fecha_limite' => 'nullable|date',
            'sla_horas' => 'nullable|integer|min:1',
            'usuario_asignado_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
            'equipo_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'asunto.required' => 'El asunto del ticket es obligatorio.',
            'asunto.max' => 'El asunto no puede exceder :max caracteres.',
            'prioridad.in' => 'La prioridad debe ser alta, media o baja.',
            'sla_horas.min' => 'El SLA debe ser de al menos 1 hora.',
        ];
    }
}
