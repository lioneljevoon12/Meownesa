<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email notifikasi MeowNesa (konfirmasi pengajuan, perubahan status, serah terima, pengingat).
 * Isi sama dengan catatan di tabel `notifikasi`; email hanyalah salah satu kanalnya.
 */
class NotifikasiMail extends Mailable
{
    public function __construct(public readonly string $jenis, public readonly string $isi)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'MeowNesa - '.$this->jenis);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notifikasi');
    }
}
