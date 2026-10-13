<?php

namespace App\Http\Requests\Concerns;

use App\Models\Booking;
use App\Rules\OnMinuteGrid;
use App\Support\OrgTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The Booking form (#795, #796, ADR-0032 §1, §10), shared by adding and changing a Booking.
 *
 * The staffing half is a date and a start and end time on the org wall clock, plus the docents
 * needed; the controller turns them into the Shift ({@see Booking::book()}, {@see Booking::change()}).
 * The client half is the Booking's own fields. The Tour and booking type must be the Group's own and
 * active; a change may keep the Booking's own retired one ($keep), never move onto another. Client,
 * leader, order number and comments are content, never translated (ADR-0004).
 */
trait DescribesBooking
{
    /**
     * Every field's rules: the staffing half, then the client half.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function bookingRules(int $groupId, ?Booking $keep = null): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_time' => ['required', 'date_format:H:i', new OnMinuteGrid],
            'ends_time' => ['required', 'date_format:H:i', 'after:starts_time', new OnMinuteGrid],
            'docents_needed' => ['required', 'integer', 'min:1', 'max:99'],
            'tour_id' => [
                'required',
                'integer',
                Rule::exists('tours', 'id')
                    ->where('group_id', $groupId)
                    ->where(fn ($query) => $query->where('active', true)->when($keep, fn ($query) => $query->orWhere('id', $keep->tour_id))),
            ],
            'booking_type_id' => [
                'required',
                'integer',
                Rule::exists('booking_types', 'id')
                    ->where('group_id', $groupId)
                    ->where(fn ($query) => $query->where('active', true)->when($keep, fn ($query) => $query->orWhere('id', $keep->booking_type_id))),
            ],
            'client' => ['required', 'string', 'max:255'],
            'visitors' => ['required', 'integer', 'min:0', 'max:99999'],
            'leader' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'order_date' => ['nullable', 'date_format:Y-m-d'],
            'comments' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The Shift's start, as a UTC instant read from the org wall clock.
     */
    public function startsAt(): CarbonImmutable
    {
        return $this->instant('starts_time');
    }

    /**
     * The Shift's end, as a UTC instant read from the org wall clock.
     */
    public function endsAt(): CarbonImmutable
    {
        return $this->instant('ends_time');
    }

    /**
     * The Booking's own fields, without the staffing half.
     *
     * @return array<string, mixed>
     */
    public function bookingAttributes(): array
    {
        return $this->safe()->except(['date', 'starts_time', 'ends_time', 'docents_needed']);
    }

    private function instant(string $field): CarbonImmutable
    {
        return CarbonImmutable::parse(OrgTime::toUtc($this->validated('date').' '.$this->validated($field)), 'UTC');
    }
}
