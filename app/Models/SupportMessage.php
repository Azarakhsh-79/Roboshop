<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'sender_user_id',
        'is_super_admin_reply',
        'message',
        'attachment_file_id',
        'is_read',
    ];

    protected $casts = [
        'is_super_admin_reply' => 'boolean',
        'is_read'              => 'boolean',
        'ticket_id'            => 'integer',
        'sender_user_id'       => 'integer',
    ];

    /**
     * ارتباط با تیکت اصلی
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /**
     * ارتباط با ارسال‌کننده پیام (کاربر)
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
