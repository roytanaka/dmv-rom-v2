<?php

namespace App\Actions;

use App\Models\HoursRecord;
use App\Models\Invitation;
use App\Models\Member;
use App\Support\ProfilePhotoStorage;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Delete a Member from the Past, Deceased or Admin list (#921, ADR-0038 §6). The Member's own rows
 * (Memberships, Category history, board positions, languages, Sign-ups, Deliveries, Sign-in log)
 * go through the existing foreign-key cascades. Refused while any Hours record exists, since those
 * are in the department's reported totals: Remove personal data is the path for them.
 */
class DeleteMember
{
    public function __construct(private readonly ProfilePhotoStorage $photos) {}

    /**
     * Why this Member cannot be deleted, as a translation key, or null when they can. An Invitation
     * host is refused too: the invitations table keeps its host (restrictOnDelete).
     */
    public function refusal(Member $member): ?string
    {
        if (HoursRecord::where('member_id', $member->id)->exists()) {
            return 'records.delete.has_hours';
        }

        if (Invitation::withTrashed()->where('host_id', $member->id)->exists()) {
            return 'records.delete.hosts_invitation';
        }

        return null;
    }

    public function delete(Member $member, Member $actor): void
    {
        if (($reason = $this->refusal($member)) !== null) {
            throw new RuntimeException(__($reason));
        }

        $memberId = $member->id;
        $photoPath = $member->getAttributes()['photo_path'] ?? null;
        $member->delete();
        $this->photos->delete($photoPath);

        Log::info('Records: Member deleted', [
            'member_id' => $memberId,
            'actor_id' => $actor->id,
            'at' => now()->toIso8601String(),
        ]);
    }
}
