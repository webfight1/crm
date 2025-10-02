<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\IcalendarGenerator\Components\Event;

class CalendarEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'location',
        'type',
        'task_id',
        'user_id',
        'attendees',
        'status',
        'uuid',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attendees' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAttendeeUsers()
    {
        return User::whereIn('id', $this->attendees ?? [])->get();
    }

    public function toICalEvent(): Event
    {
        return Event::create()
            ->name($this->title)
            ->description($this->description)
            ->startsAt($this->start_time)
            ->endsAt($this->end_time)
            ->location($this->location)
            ->status($this->status)
            ->uniqueIdentifier($this->uuid);
    }
}
