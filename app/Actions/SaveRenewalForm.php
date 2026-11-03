<?php

namespace App\Actions;

use App\Enums\Category;
use App\Enums\RenewalEligibility;
use App\Models\FeeCategory;
use App\Models\Member;
use App\Support\Applicants\ApplicantOptions;
use App\Support\Notices\RenewalSubmittedNoticeWriter;
use App\Support\OrgTime;
use App\Support\Renewal\RenewalPlacements;
use Illuminate\Support\Collection;

/**
 * Step 2 of the Renewal (#940, ADR-0038 §9): the renewal form, read and saved, as the Legacy app's
 * `_get_dialog_dmvrenewalform` and `_saveRenewalForm`. A Member whose renewal year is the year the
 * Renewal serves has submitted already and reopens the form in edit mode, from what they saved.
 */
class SaveRenewalForm
{
    /** The choice that is no fee row: the Member leaves the DMV. */
    public const RESIGN = 'Resign';

    /** The contact fields Records is told about when they change, in the Notice's order. */
    public const TRACKED = [
        'membership_number', 'first_name', 'last_name',
        'address_street', 'address_city', 'address_province', 'address_postal_code',
        'phone', 'alternate_phone', 'business_phone',
        'emergency_contact', 'emergency_contact_number',
    ];

    /** The codes whose Member need not name a placement. */
    public const NO_PLACEMENT_NEEDED = ['LOA', self::RESIGN, 'Sust', 'Sust-SR'];

    public function __construct(private RenewalSubmittedNoticeWriter $notices) {}

    public function editing(Member $member): bool
    {
        return (int) ($member->getAttributes()['renewal_year'] ?? 0) === $member->renewalServedYear();
    }

    /**
     * The checklist: the saved one in edit mode, else built from the Memberships.
     *
     * @return list<array{name: string, position: string, checked: bool}>
     */
    public function checklist(Member $member): array
    {
        return $this->editing($member)
            ? RenewalPlacements::parse($member->renewal_placements)
            : RenewalPlacements::fromMemberships($member);
    }

    /** @return Collection<int, array{id: int, name: string}> */
    public function groups(): Collection
    {
        return ApplicantOptions::placements();
    }

    /** @return Collection<int, FeeCategory> */
    public function categories(): Collection
    {
        return FeeCategory::query()->where('active', true)->orderBy('id')->get();
    }

    /** @return list<string> */
    public function categoryCodes(): array
    {
        return [...$this->categories()->pluck('code')->all(), self::RESIGN];
    }

    /**
     * The form's values from the record. A past expiry is blanked on a first submission, as the
     * Legacy app does, so the Member types the renewed card's date.
     *
     * @return array<string, mixed>
     */
    public function fields(Member $member): array
    {
        $editing = $this->editing($member);
        $expiry = $member->membership_expiry;
        $other = $editing
            ? RenewalPlacements::parseOther($member->renewal_other, $this->groups())
            : RenewalPlacements::parseOther(null, $this->groups());

        return [
            'membership_number' => $member->membership_number,
            'membership_lifetime' => (bool) $member->membership_lifetime,
            'membership_expiry' => $expiry === null || (! $editing && $expiry->lt(OrgTime::now()->startOfDay()))
                ? null
                : $expiry->toDateString(),
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'address_street' => $member->address_street,
            'address_city' => $member->address_city,
            'address_province' => $member->address_province,
            'address_postal_code' => $member->address_postal_code,
            'phone' => $member->phone,
            'alternate_phone' => $member->alternate_phone,
            'business_phone' => $member->business_phone,
            'emergency_contact' => $this->emergencyName($member->emergency_contact),
            'emergency_contact_number' => $member->emergency_contact_number,
            'other' => $other['other'],
            'other_texts' => $other['texts'],
            'category' => $editing ? $member->renewal_status : null,
        ];
    }

    /**
     * Save the form and tell Records. Returns whether the flow ends here: Resign, LOA, or Active
     * to Sustaining, which Records follows up in person.
     *
     * @param  array<string, mixed>  $data  the validated request
     */
    public function save(Member $member, array $data): bool
    {
        $editing = $this->editing($member);
        $year = $member->renewalServedYear();
        // Read before the save: the renewal status written below feeds the eligibility.
        $provisional = $member->renewalEligibility() === RenewalEligibility::ProvisionalToActive;
        $code = $data['category'];
        $oldCode = $editing ? (string) $member->renewal_status : $this->legacyCode($member->category);
        $groups = $this->groups();

        $checked = array_map('intval', $data['placements'] ?? []);
        $items = collect($this->checklist($member))
            ->map(fn (array $item, int $i): array => [...$item, 'checked' => in_array($i, $checked, true)])
            ->all();
        $other = collect([0, 1])->map(fn (int $i): array => [
            'group_id' => isset($data['other'][$i]['group_id']) ? (int) $data['other'][$i]['group_id'] : null,
            'position' => $data['other'][$i]['position'] ?? 'Member',
        ])->all();
        $texts = collect([0, 1])->map(fn (int $i): string => trim((string) ($data['other_texts'][$i] ?? '')))->all();

        $changes = $this->changes($member, $data);
        $categoryChange = $this->categoryChanged($oldCode, $code, $editing) ? ['from' => $oldCode, 'to' => $code] : null;

        $member->forceFill([
            'membership_number' => $data['membership_number'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'address_street' => $data['address_street'] ?? null,
            'address_city' => $data['address_city'] ?? null,
            'address_province' => $data['address_province'] ?? null,
            'address_postal_code' => $data['address_postal_code'] ?? null,
            'phone' => $data['phone'] ?? null,
            'alternate_phone' => $data['alternate_phone'] ?? null,
            'business_phone' => $data['business_phone'] ?? null,
            'emergency_contact' => trim($year.' '.($data['emergency_contact'] ?? '')),
            'emergency_contact_number' => $data['emergency_contact_number'] ?? null,
            'renewal_status' => $code,
            'renewal_year' => $year,
            'renewal_placements' => RenewalPlacements::render($items),
            'record_form_placements' => RenewalPlacements::renderRecordForm($items, $code),
            'renewal_other' => RenewalPlacements::renderOther($other, $texts, $groups),
            'record_form_other' => RenewalPlacements::renderOtherRecordForm($other, $texts, $groups),
        ]);

        // Life, or a date; LOA and Resign may leave the card as it was.
        if ($data['membership_lifetime'] ?? false) {
            $member->forceFill(['membership_lifetime' => true, 'membership_expiry' => null]);
        } elseif (filled($data['membership_expiry'] ?? null)) {
            $member->forceFill(['membership_lifetime' => false, 'membership_expiry' => $data['membership_expiry']]);
        }

        // Records sets the fee by hand for a Provisional Member going Active.
        if (! $provisional) {
            $fee = FeeCategory::query()->where('code', $code)->first();
            $member->forceFill(['amount_paid' => $fee?->amount, 'fee_category' => $fee?->name]);
        }

        $member->save();

        $expired = ! $member->membership_lifetime
            && $member->membership_expiry !== null
            && $member->membership_expiry->lt(OrgTime::now()->startOfDay());

        $this->notices->write($member, $year, $editing, $changes, $categoryChange, $expired);

        return in_array($code, [self::RESIGN, 'LOA'], true)
            || ($this->base($code) === 'Sust' && $this->base($oldCode) === 'Active');
    }

    /**
     * The tracked fields the Member changed, as the Legacy app's `renewalChange` lists them.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{field: string, from: string, to: string}>
     */
    private function changes(Member $member, array $data): array
    {
        $changes = [];

        foreach (self::TRACKED as $field) {
            $from = $field === 'emergency_contact'
                ? (string) $this->emergencyName($member->emergency_contact)
                : (string) $member->getAttribute($field);
            $to = trim((string) ($data[$field] ?? ''));

            if (trim($from) !== $to) {
                $changes[] = ['field' => $field, 'from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /**
     * Whether the Category choice differs from the Member's. A first submission compares the
     * family only (Active Senior is still Active), as the Legacy app strips the suffix.
     */
    private function categoryChanged(string $old, string $new, bool $editing): bool
    {
        return $editing ? $old !== $new : $this->base($old) !== $this->base($new);
    }

    /** The fee-table code of a Category, or its own value when the table has none. */
    private function legacyCode(?Category $category): string
    {
        return match ($category) {
            Category::Active => 'Active',
            Category::Sustaining => 'Sust',
            default => (string) $category?->value,
        };
    }

    private function base(string $code): string
    {
        return explode('-', $code)[0];
    }

    /** The emergency contact without the year the Legacy app puts in front of it. */
    private function emergencyName(?string $contact): ?string
    {
        return $contact !== null && preg_match('/^20\d\d (.*)$/s', $contact, $m) === 1 ? $m[1] : $contact;
    }
}
