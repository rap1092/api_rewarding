<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Farming extends Model
{
    use HasFactory;
    protected $table="farmings";
    protected $fillable=[
        'userTgId',
        'transactionId',
        'startFarmingDate',
        'targetFarmingDate',
        'reward',
        'status',

    ];
}
