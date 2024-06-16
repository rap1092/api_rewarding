<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TgMembers extends Model
{
    use HasFactory;
    protected $table="members";
    protected $fillable = [
        'userTgId','refferalTgId',
        'fullname','usernameTg','ipaddress','country', 
        'city','uri','referer','userinfo','org'  
    ];
}
