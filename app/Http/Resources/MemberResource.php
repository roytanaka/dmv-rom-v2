<?php

namespace App\Http\Resources;

use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The single centralized member payload (#154, ADR-0017 §field-level visibility).
 * Every member sent to the frontend routes through here — no controller hand-builds
 * a member array, so contact PII can never leak via a forgotten payload.
 *
 * The shape is an allowlist, least-privilege by default:
 *
 * - Always public to any logged-in member: id, name, photo (if uploaded), and the
 *   member's Groups + roles — enough for a useful directory.
 * - Gated behind the `viewContact` ability: email, phone, and every other contact
 *   field. A field that is not added to the gated block below is simply absent —
 *   so a newly added column is private until someone deliberately exposes it.
 *
 * @property Member $resource
 */
class MemberResource extends JsonResource
{
    /**
     * Drop the default `data` wrapper so the member resolves as a flat object when
     * passed straight into an Inertia prop.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewContact = (bool) $request->user()?->can('viewContact', $this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            // null until the photo-upload feature lands.
            'photo' => $this->photo_path,
            'groups' => $this->whenLoaded('memberships', fn () => $this->memberships
                ->map(fn (GroupMember $membership) => [
                    'name' => $membership->group->name,
                    'slug' => $membership->group->slug,
                    'roles' => $membership->roles
                        ->map(fn (GroupMemberRole $role) => $role->role->value)
                        ->all(),
                ])
                ->values()
                ->all()),
            // Absent (not null) when unauthorized — mergeWhen omits the key entirely.
            $this->mergeWhen($canViewContact, fn () => [
                'email' => $this->email,
                'phone' => $this->phone,
            ]),
        ];
    }
}
