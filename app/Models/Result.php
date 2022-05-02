<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'match_id',
        'team_id',
    ];

    public function winner_of_match()
    {
        return $this->hasOne(Match::class);
    }
}
