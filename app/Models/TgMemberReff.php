<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TgMemberReff extends Model
{
    use HasFactory;
    protected $table="reward_refferals";
    protected $fillable = [
        'userTgId','userTgIdJoined',
        'amount'
    ];

}
