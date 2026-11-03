<?php

namespace App\Actions;

use App\Enums\Category;
use App\Models\Member;
use App\Support\OrgTime;
use App\Support\ProfilePhotoStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Remove personal data (#921, ADR-0038 §6): the yearly privacy clean-up. Every Resigned or
 * Deceased Member whose status date is before 1 January of the chosen year loses the personal
 * fields and moves to the Past list; the name, Category history, board positions and Hours
 * records stay. Old Applicants are deleted too, once the Applicants PRD (#914) adds them.
 */
class RemovePersonalData
{
    /** The columns the run blanks. Address, phones and contact, card, dues, background, awards, notes, photo. */
    private const BLANKED = [
        'email',
        'phone',
        'alternate_phone',
        'business_phone',
        'address_street',
        'address_city',
        'address_province',
        'address_postal_code',
        'address_country',
        'membership_number',
        'membership_expiry',
        'membership_lifetime',
        'amount_paid',
        'paid_through_year',
        'volunteer_experience',
        'past_experience',
        'education',
        'next_award_year',
        'emergency_contact',
        'emergency_contact_number',
        'photo_path',
        'notes',
    ];

    public function __construct(private readonly ProfilePhotoStorage $photos) {}

    /** The year the page opens on: seven years ago (ADR-0038 §6). */
    public static function defaultYear(): int
    {
        return OrgTime::now()->year - 7;
    }

    /**
     * The Members the run touches: Resigned or Deceased group, status date before 1 January of
     * the year, data not already removed.
     *
     * @return Builder<Member>
     */
    public function members(int $year): Builder
    {
        return Member::query()
            ->whereIn('category', Category::departed())
            ->whereNull('personal_data_removed_at')
            ->where('status_date', '<', sprintf('%04d-01-01', $year));
    }

    /** The Applicants the run deletes: none until the Applicants PRD (#914) adds them with their model. */
    public function applicantCount(int $year): int
    {
        return 0;
    }

    /** Run the clean-up and return how many Members it touched. */
    public function run(int $year, Member $actor): int
    {
        $photoPaths = [];

        $count = DB::transaction(function () use ($year, $actor, &$photoPaths): int {
            $count = 0;

            $this->members($year)->chunkById(100, function ($members) use ($actor, &$photoPaths, &$count): void {
                foreach ($members as $member) {
                    $photoPaths[] = $member->getAttributes()['photo_path'] ?? null;

                    $member->forceFill([
                        ...array_fill_keys(self::BLANKED, null),
                        'photo_public' => false,
                        'personal_data_removed_at' => now(),
                        'updated_by' => $actor->id,
                    ]);
                    // A Remember me cookie stops matching.
                    $member->setRememberToken(Str::random(60));
                    $member->save();

                    $member->languages()->delete();
                    $member->skills()->detach();
                    $count++;
                }
            });

            return $count;
        });

        // After the commit, so a rolled-back run never strands a Member without their photo.
        foreach ($photoPaths as $path) {
            $this->photos->delete($path);
        }

        Log::info('Records: personal data removed', [
            'count' => $count,
            'year' => $year,
            'actor_id' => $actor->id,
            'at' => now()->toIso8601String(),
        ]);

        return $count;
    }
}
