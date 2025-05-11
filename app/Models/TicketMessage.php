<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\TicketMessage
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $message
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Ticket $ticket 关联的工单
 * @property-read bool $is_from_user 消息是否由工单发起人发送
 * @property-read bool $is_from_admin 消息是否由管理员发送
 */
class TicketMessage extends Model
{
    protected $table = 'v2_ticket_message';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    protected $appends = ['is_from_user', 'is_from_admin'];

    /**
     * 关联的工单
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    /**
     * 判断消息是否由工单发起人发送
     */
    public function getIsFromUserAttribute(): bool
    {
        return $this->ticket->user_id === $this->user_id;
    }

    /**
     * 判断消息是否由管理员发送
     */
    public function getIsFromAdminAttribute(): bool
    {
        return $this->ticket->user_id !== $this->user_id;
    }
}
