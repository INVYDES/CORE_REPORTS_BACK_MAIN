<?php

namespace App\Mail;

use App\Models\Dependencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LicenciaVencidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Dependencia $dependencia,
        public string $fecha,
    ) {
    }

    public function build(): void
    {
        $this->subject('Tu licencia de '.config('app.name').' venció')
            ->view('emails.licencia-vencida');
    }
}
