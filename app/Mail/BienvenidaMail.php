<?php

namespace App\Mail;

use App\Models\Licencia;
use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BienvenidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Usuario $usuario,
        public ?Licencia $licencia = null,
    ) {
    }

    public function build(): void
    {
        $this->subject('Bienvenido a '.config('app.name'))
            ->view('emails.bienvenida');
    }
}
