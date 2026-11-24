<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoLicencia extends Model
{
    protected $table = 'pagos_licencia';

    protected $fillable = [
        'dependencia_id',
        'licencia_id',
        'preferencia_id',
        'payment_id',
        'external_reference',
        'estado',
        'metodo',
        'monto',
        'moneda',
        'pagado_en',
        'procesado_en',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'pagado_en' => 'datetime',
        'procesado_en' => 'datetime',
    ];

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class, 'dependencia_id');
    }

    public function licencia(): BelongsTo
    {
        return $this->belongsTo(Licencia::class, 'licencia_id');
    }
}
