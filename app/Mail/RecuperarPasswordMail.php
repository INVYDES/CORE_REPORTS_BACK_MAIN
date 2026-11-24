<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecuperarPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Usuario $usuario,
        public string $enlace,
        public int $expiraMinutos,
    ) {
    }

    public function build(): void
    {
        $this->subject('Restablece tu contraseña de '.config('app.name'))
            ->view('emails.recuperar-password');
    }
}
