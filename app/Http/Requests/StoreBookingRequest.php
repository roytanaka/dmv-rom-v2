<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Rules\OnMinuteGrid;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adding a Booking to a Group (#795, ADR-0032 §1, §3, §10). Authorized through the BookingPolicy's
 * `create` gate on the route-bound Group (bookings and scheduling on, actor a Booker or Chair).
 *
 * The staffing half is a date and a start and end time on the org wall clock, plus the docents
 * needed; the controller turns them into the Shift ({@see Booking::book()}). The client half is the
 * Booking's own fields. The Tour and booking type must be the Group's own and active: a retired
 * Tour or type is no longer offered. Client, leader, order number and comments are content, never
 * translated (ADR-0004). The Group must have a group-tour shift kind set (Group Settings) for the
 * Shift to take.
 */
class StoreBookingRequest extends FormRequest
{
    /**
     * Authorize against the BookingPolicy on the route-bound Group.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Booking::class, $this->route('group')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...self::staffingRules(),
            ...self::clientRules($this->route('group')->getKey()),
        ];
    }

    /**
     * The staffing half's rules — date, start, end and docents needed — shared with changing a
     * Booking (#796).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function staffingRules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_time' => ['required', 'date_format:H:i', new OnMinuteGrid],
            'ends_time' => ['required', 'date_format:H:i', 'after:starts_time', new OnMinuteGrid],
            'docents_needed' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * The client half's rules, shared with changing a Booking (#796).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function clientRules(int $groupId): array
    {
        return [
            'tour_id' => [
                'required',
                'integer',
                Rule::exists('tours', 'id')->where('group_id', $groupId)->where('active', true),
            ],
            'booking_type_id' => [
                'required',
                'integer',
                Rule::exists('booking_types', 'id')->where('group_id', $groupId)->where('active', true),
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
     * Refuse while the Group has no group-tour shift kind to give the Shift.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('group')->group_tour_shift_kind_id === null) {
                $validator->errors()->add('date', trans('group.bookings.no_shift_kind'));
            }
        });
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
        return CarbonImmutable::parse($this->validated('date').' '.$this->validated($field), config('app.org_timezone'))->utc();
    }
}
