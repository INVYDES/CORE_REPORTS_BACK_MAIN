<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folio' => 'sometimes|string|max:50',
            'asunto' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'solicitante' => 'nullable|string|max:150',
            'cargo' => 'nullable|string|max:100',
            'categoria' => 'nullable|in:preventivo,correctivo,instalacion,mejora,diagnostico',
            'area_id' => 'nullable|exists:areas,id',
            'equipo_id' => 'nullable|exists:equipos,id',
            'usuario_asignado_id' => 'nullable|exists:usuarios,id',
            'fecha_asignacion' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_asignacion',
            'prioridad' => 'nullable|string|max:50',
        ];
    }
}
