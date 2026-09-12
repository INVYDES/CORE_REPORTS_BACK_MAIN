<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ReportePolicy::create se aplica en el controlador
    }

    public function rules(): array
    {
        return [
            'folio' => 'sometimes|string|max:50',
            'tipo' => 'required|in:ticket,servicio,libre',
            'ticket_id' => 'nullable|integer',
            'servicio_id' => 'nullable|integer',
            'categoria' => 'nullable|in:preventivo,correctivo,diagnostico,instalacion,mejora',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'desarrollo' => 'nullable|string',
            'estatus' => 'sometimes|in:abierto,parcial,finalizado,descartado',
            'area_id' => 'nullable|integer',
            'equipo_id' => 'nullable|integer',
            'responsable_id' => 'nullable|integer',
            'hora_salida' => 'nullable|date',
            'hora_llegada' => 'nullable|date',
            'hora_inicio_diagnostico' => 'nullable|date',
            'hora_inicio_trabajo' => 'nullable|date',
            'hora_fin_trabajo' => 'nullable|date',
            'hora_regreso' => 'nullable|date',
            'es_retrabajo' => 'sometimes|boolean',
            'reporte_origen_id' => 'nullable|integer',
            'costo_mano_obra' => 'sometimes|numeric|min:0',
            'costo_materiales' => 'sometimes|numeric|min:0',
            'ejecutores' => 'sometimes|array',
            'ejecutores.*' => 'integer',
            'materiales' => 'sometimes|array',
            'materiales.*.material_id' => 'required_with:materiales|integer',
            'materiales.*.cantidad' => 'required_with:materiales|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de reporte es obligatorio.',
            'tipo.in' => 'El tipo debe ser ticket, servicio o libre.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
            'materiales.*.cantidad.min' => 'La cantidad de material debe ser mayor a cero.',
        ];
    }
}
