<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'title',
        'context',
        'source_type',
        'model_used'
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
