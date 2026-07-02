<?php

namespace App\Http\Resources;

use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The single centralized member payload (#154, ADR-0017 §field-level visibility).
 * Every member sent to the frontend routes through here — no controller hand-builds
 * a member array, so contact PII can never leak via a forgotten payload.
 *
 * The shape is an allowlist, least-privilege by default:
 *
 * - Always public to any logged-in member: id, first/last name, photo (if
 *   uploaded), DMV-wide standing, and the member's Groups + roles — enough for a
 *   useful directory.
 * - Gated behind the `viewContact` ability (peer-visible tier): email and the
 *   three phone numbers. A field not added to a gated block is simply absent —
 *   so a newly added column is private until deliberately exposed.
 * - Gated behind the `viewAddress` ability (Records-only tier): the home address.
 *   Never present in a peer-visible payload, even when `viewContact` is granted
 *   (#232, ADR-0017 §field-level visibility).
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
     * Whether this instance may carry contact PII at all. The directory list flips
     * it off ({@see directoryCollection()}) so the roster never carries email/phone
     * and never even asks `viewContact` per row — contact is a profile-only concern.
     */
    protected bool $includeContact = true;

    /**
     * Build the directory-list collection: contact PII is suppressed for every row
     * up front, so the ~500-row roster never carries email/phone for any viewer and
     * the gate is never evaluated per row (#169, ADR-0017).
     *
     * @param  iterable<Member>  $resource
     */
    public static function directoryCollection(iterable $resource): AnonymousResourceCollection
    {
        $collection = static::collection($resource);
        $collection->collection->each(function (self $member): void {
            $member->includeContact = false;
        });

        return $collection;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewContact = $this->includeContact
            && (bool) $request->user()?->can('viewContact', $this->resource);

        // The home address is a stricter, Records-only tier than the peer-gated
        // contact block: never present in a peer-visible payload, even a gated one
        // (#232, ADR-0017). Suppressed on the directory list like the rest of
        // contact, then gated behind `viewAddress` (self / Records / super-tier).
        $canViewAddress = $this->includeContact
            && (bool) $request->user()?->can('viewAddress', $this->resource);

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            // Public avatar URL (#233), or null when the member has uploaded none.
            // Always public — a face is opt-in by uploading (contrast gated contact PII).
            'photo' => $this->photo_url,
            // DMV-wide standing (the Member's Category, glossary-named "standing").
            // Not PII — drives the directory/profile standing badge. The raw enum
            // attribute stays absent; the frontend maps this value to a chrome label.
            'standing' => $this->category->value,
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
            // The three phones are peer-gated contact PII; primary plus the two
            // optional secondary numbers (#232).
            $this->mergeWhen($canViewContact, fn () => [
                'email' => $this->email,
                'phone' => $this->phone,
                'alternate_phone' => $this->alternate_phone,
                'business_phone' => $this->business_phone,
            ]),
            // Records-only tier: the address key itself is absent for anyone but the
            // member, Records, or super-tier — never leaked to a peer viewer (#232).
            $this->mergeWhen($canViewAddress, fn () => [
                'address' => [
                    'street' => $this->address_street,
                    'city' => $this->address_city,
                    'province' => $this->address_province,
                    'postal_code' => $this->address_postal_code,
                    'country' => $this->address_country,
                ],
            ]),
        ];
    }
}
