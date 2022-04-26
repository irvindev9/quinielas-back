<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Match extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'team_id_2',
        'week_id',
    ];

    public function team_1()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function team_2()
    {
        return $this->belongsTo(Team::class, 'team_id_2');
    }

    public function winner()
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }
}
