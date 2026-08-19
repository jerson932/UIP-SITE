<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Correo generico que entrega, por SMTP, el asunto/cuerpo ya resueltos de
// una plantilla de "plantillas_correo" (Fase 11). El armado de placeholders
// y el registro en la bandeja "correos_enviados" vive en
// App\Services\NotificacionService — este Mailable solo se encarga del
// envio en si.
class PlantillaCorreoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $asuntoCorreo,
        public string $cuerpoCorreo,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asuntoCorreo);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.plantilla',
            with: ['cuerpo' => $this->cuerpoCorreo],
        );
    }
}
