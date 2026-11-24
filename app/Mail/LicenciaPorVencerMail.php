<?php

namespace App\Mail;

use App\Models\Dependencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LicenciaPorVencerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Dependencia $dependencia,
        public int $dias,
        public string $fecha,
    ) {
    }

    public function build(): void
    {
        $this->subject('Tu licencia vence en '.$this->dias.' '.($this->dias === 1 ? 'día' : 'días'))
            ->view('emails.licencia-por-vencer');
    }
}
