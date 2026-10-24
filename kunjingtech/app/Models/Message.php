<?php
/**
 * 联系留言模型（演示 KunJingPHP ORM）
 */

namespace Site\Models;

use KunJing\Framework\Database\Model;

class Message extends Model
{
    protected string $table = 'contact_messages';

    protected array $fillable = ['name', 'phone', 'email', 'type', 'content', 'ip'];
}
