<?php

namespace App\Mail;

use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Notificacion $notificacion) {}

    public function build()
    {
        return $this->subject($this->notificacion->asunto ?? 'Notificación CoreReports')
            ->view('emails.notificacion')
            ->with(['notif' => $this->notificacion]);
    }
}
