<?php

namespace App\Mail;

use App\Models\Reporte;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportePdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reporte $reporte,
        public string $pdfContent,
        public ?string $comentario = null
    ) {}

    public function build(): self
    {
        $folio = $this->reporte->folio ?: 'REP-000';
        $empresaNombre = $this->reporte->dependencia?->nombre ?: 'CoreReports';

        return $this->subject("Reporte de Servicio [{$folio}] - {$empresaNombre}")
            ->view('emails.reporte_pdf')
            ->with([
                'reporte' => $this->reporte,
                'empresa' => $this->reporte->dependencia,
                'comentario' => $this->comentario,
            ])
            ->attachData($this->pdfContent, "{$folio}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}

