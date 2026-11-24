<?php

namespace App\Mail;

use App\Models\Dependencia;
use App\Models\Licencia;
use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PagoConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Usuario $usuario,
        public Dependencia $dependencia,
        public Licencia $licencia,
        public float $monto,
        public ?string $referencia = null,
    ) {
    }

    public function build(): void
    {
        $this->subject('Pago confirmado: '.$this->licencia->nombre)
            ->view('emails.pago-confirmado');
    }
}
