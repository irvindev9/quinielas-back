<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Week extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'name',
        'end_date',
        'is_open_forced',
        'is_forced_open_quiniela'
    ];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function matches()
    {
        return $this->hasMany(Play::class);
    }
}
