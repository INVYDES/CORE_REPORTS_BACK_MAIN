<?php

namespace App\Mail;

use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificacionCuentaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Usuario $usuario,
        public string $codigo,
        public string $enlace,
        public CarbonInterface $expira,
    ) {
    }

    public function build(): void
    {
        $this->subject('Verifica tu correo en '.config('app.name'))
            ->view('emails.verificacion-cuenta');
    }
}
