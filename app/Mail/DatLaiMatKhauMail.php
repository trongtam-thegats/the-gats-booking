<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Thu gui nhan vien khi quan tri vien bam dat lai mat khau
 * hoac nhan vien tu yeu cau quen mat khau.
 */
class DatLaiMatKhauMail extends Mailable
{
    public function __construct(
        public User $user,
        public string $resetUrl,
        public bool $laTaiKhoanMoi = false,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->laTaiKhoanMoi
            ? '[The Gats] Thiết lập mật khẩu cho tài khoản quản trị mới'
            : '[The Gats] Yêu cầu đặt lại mật khẩu tài khoản quản trị';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dat-lai-mat-khau',
            text: 'emails.dat-lai-mat-khau-text',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'laTaiKhoanMoi' => $this->laTaiKhoanMoi,
            ],
        );
    }
}
