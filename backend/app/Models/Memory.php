<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use App\Observers\MemoryObserver;

#[ObservedBy(MemoryObserver::class)]
class Memory extends Model
{
    protected $fillable = [
        'title',
        'category',
        'description',
        'image_path',
        'date',
        'location',
        'is_locked',
        'unlock_question',
        'unlock_answer',
    ];
}
