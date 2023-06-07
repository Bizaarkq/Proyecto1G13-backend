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

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($from, $name, $subject, $view, $data)
    {
        $this->desde = $from;
        $this->nombre = $name;
        $this->asunto = $subject;
        $this->vista = $view;
        $this->informacion = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    public function build()
    {
        return $this
        ->from($this->desde, $this->nombre)
        ->subject($this->asunto)
        ->view($this->vista)
        ->with($this->informacion);

    }
}
