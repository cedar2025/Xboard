<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'v2_ticket';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    const STATUS_OPENING = 0;
    const STATUS_CLOSED = 1;
    public static $statusMap = [
        self::STATUS_OPENING => '开启',
        self::STATUS_CLOSED => '关闭'
    ];

    public function message()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id', 'id');
    }
}
