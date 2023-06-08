<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Correo extends Mailable
{
    use Queueable, SerializesModels;

    public $desde;
    public $nombre;
    public $asunto;
    public $vista;
    public $informacion;
    public $adjunto;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($from, $name, $subject, $view, $data, $adjunto = null)
    {
        $this->desde = $from;
        $this->nombre = $name;
        $this->asunto = $subject;
        $this->vista = $view;
        $this->informacion = $data;
        $this->adjunto = $adjunto;
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    public function build()
    {
        return $this->adjunto ? 
        $this->from($this->desde, $this->nombre)
        ->subject($this->asunto)
        ->view($this->vista)
        ->with($this->informacion)
        ->attach($this->adjunto,
        [
            'as' => 'documento-adjunto.pdf',
            'mime' => 'application/pdf',
        ])
        : $this->from($this->desde, $this->nombre)
        ->subject($this->asunto)
        ->view($this->vista)
        ->with($this->informacion);
    }
}
