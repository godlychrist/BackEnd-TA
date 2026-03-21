<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyUserAccount extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $url;

    /**
     * Create a new message instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
        // Link dinámico hacia tu Frontend (Vue) con el TOKEN del usuario
        $this->url = env('FRONTEND_URL', 'http://localhost:8080') . '/verify-email?token=' . $user->verification_token;
    }

    /**
     * Get the message envelope.
     */
    public function envelope()
    {
        return new Envelope(
            subject: '🎮 TicoAutos - Activa tu cuenta para comprar y vender',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content()
    {
        return new Content(
            markdown: 'emails.verify-account',
        );
    }

    public function attachments()
    {
        return [];
    }
}
