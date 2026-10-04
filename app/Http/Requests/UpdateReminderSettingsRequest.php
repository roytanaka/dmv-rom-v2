<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing a Group's Reminder settings (#486, PRD #479, ADR-0024 §7) — the on/off switch and
 * the lead days, the pair the daily pass reads to decide which Sign-ups earn a Reminder and
 * how far ahead. The mutation is authorized structurally here: `authorize()` resolves the
 * route-bound Group and delegates to the SchedulePolicy's `updateReminders` gate (a Scheduler
 * or Chair of the Group, plus the super-tier, only while scheduling is on), never returning
 * `true` blindly. The controller passes `validated()`, never `all()`.
 */
class UpdateReminderSettingsRequest extends FormRequest
{
    /**
     * Authorize against the SchedulePolicy. The ability is reached on the Group, so the
     * policy is resolved by the class name passed alongside it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('updateReminders', [Schedule::class, $this->route('group')]);
    }

    /**
     * Both settings are required — the Reminders block always submits the pair. The lead days
     * is a whole count of at least one day (a Reminder the same day the pass runs is still a
     * day of notice) and capped well above the department's 3–4 to keep a fat-fingered value
     * from queueing weeks of premature mail.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reminders_enabled' => ['required', 'boolean'],
            'reminder_lead_days' => ['required', 'integer', 'min:1', 'max:90'],
        ];
    }
}
