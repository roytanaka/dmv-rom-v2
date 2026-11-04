<?php

namespace App\Support\Renewal;

use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;

/**
 * The placements part of the renewal form (#940, ADR-0038 §9). The Legacy app stores four HTML
 * fragments on the Member: the checklist as the form shows it (`renewal_placements`), the two
 * "other placement" selects and two free texts as the form shows them (`renewal_other`), and the
 * read-only twins the record form prints (`record_form_placements`, `record_form_other`). This
 * class renders all four server-side, every value escaped, and reads the two form fragments back
 * for edit mode, its own and the Legacy app's alike.
 *
 * A checklist item is `{name, position, checked}`; an other placement is `{group_id, position}`.
 */
final class RenewalPlacements
{
    /** The position choices of an "other placement", stored as written. */
    public const POSITIONS = ['Member', 'Chair', 'Co-Chair', 'Vice-Chair', 'Auxiliary'];

    /**
     * The checklist for a first submission: one item per belonging Membership in an active Group
     * below the org root, its roles as the position ("Member" when none), sorted by Group name.
     *
     * @return list<array{name: string, position: string, checked: bool}>
     */
    public static function fromMemberships(Member $member): array
    {
        $active = Group::query()->active()->where('slug', '!=', Group::ROOT_SLUG)->pluck('id');

        return $member->memberships()
            ->whereIn('status', MembershipStatus::belongingValues())
            ->whereIn('group_id', $active)
            ->with(['group', 'roles'])
            ->get()
            ->sortBy(fn (GroupMember $membership): string => $membership->group->name)
            ->map(fn (GroupMember $membership): array => [
                'name' => $membership->group->name,
                'position' => $membership->roles->isEmpty()
                    ? 'Member'
                    : $membership->roles->map(fn (GroupMemberRole $role): string => __('group.role.'.$role->role->value, [], 'en'))->join(', '),
                'checked' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * Read a saved checklist back: one item per row, its checkbox, name cell and position input.
     *
     * @return list<array{name: string, position: string, checked: bool}>
     */
    public static function parse(?string $html): array
    {
        $xpath = self::xpath($html);

        if ($xpath === null) {
            return [];
        }

        $items = [];

        foreach ($xpath->query('//tr') as $row) {
            $cells = $xpath->query('./td', $row);

            if ($cells->length < 3) {
                continue;
            }

            $checkbox = $xpath->query('.//input[@type="checkbox"]', $cells->item(0))->item(0);
            $input = $xpath->query('.//input', $cells->item(2))->item(0);
            $selected = $xpath->query('.//option[@selected]', $cells->item(2))->item(0);

            $items[] = [
                'name' => trim($cells->item(1)->textContent),
                'position' => trim($input instanceof DOMElement ? $input->getAttribute('value') : ($selected?->textContent ?? $cells->item(2)->textContent)),
                'checked' => $checkbox instanceof DOMElement && $checkbox->hasAttribute('checked'),
            ];
        }

        return $items;
    }

    /**
     * Read a saved "other placements" fragment back. A select's choice maps to an active Group by
     * id (this app's value) or by name (the Legacy app's committee symbol is not a Group id).
     *
     * @param  Collection<int, array{id: int, name: string}>  $groups  the active Groups offered
     * @return array{other: list<array{group_id: int|null, position: string}>, texts: list<string>}
     */
    public static function parseOther(?string $html, Collection $groups): array
    {
        $other = [['group_id' => null, 'position' => 'Member'], ['group_id' => null, 'position' => 'Member']];
        $texts = ['', ''];
        $xpath = self::xpath($html);

        if ($xpath === null) {
            return ['other' => $other, 'texts' => $texts];
        }

        foreach ([0, 1] as $i) {
            $choice = $xpath->query("//select[@id='pl{$i}']/option[@selected]")->item(0);
            $position = $xpath->query("//select[@id='ch{$i}']/option[@selected]")->item(0);

            if ($choice instanceof DOMElement) {
                $value = $choice->getAttribute('value');
                $name = trim($choice->textContent);
                $match = $groups->first(fn (array $group): bool => (string) $group['id'] === $value)
                    ?? $groups->first(fn (array $group): bool => $group['name'] === $name);
                $other[$i]['group_id'] = $match['id'] ?? null;
            }

            if ($position !== null && in_array(trim($position->textContent), self::POSITIONS, true)) {
                $other[$i]['position'] = trim($position->textContent);
            }

            $text = $xpath->query("//input[@id='OtherCommittee_".($i + 1)."']")->item(0);
            $texts[$i] = $text instanceof DOMElement ? trim($text->getAttribute('value')) : '';
        }

        return ['other' => $other, 'texts' => $texts];
    }

    /**
     * The checklist as the form shows it (the Legacy app's `Renewal_Committees`).
     *
     * @param  list<array{name: string, position: string, checked: bool}>  $items
     */
    public static function render(array $items): string
    {
        return collect($items)->map(fn (array $item): string => "<tr><td><input type='checkbox' name='committeeName[]' ".($item['checked'] ? ' checked ' : '').' /></td>'
            .'<td>'.e($item['name'])."</td><td><input type='text' size='32' value='".e($item['position'])."' /></td></tr>")->join('');
    }

    /**
     * The checklist as the record form prints it, the chosen Category first (`RecordForm_Committees`).
     *
     * @param  list<array{name: string, position: string, checked: bool}>  $items
     */
    public static function renderRecordForm(array $items, string $category): string
    {
        return '<tr><td>Category</td><td>'.e($category).'</td></tr>'
            .collect($items)->map(fn (array $item): string => "<tr><td><input type='checkbox' ".($item['checked'] ? ' checked ' : '').'/> '
                .e($item['name']).'</td><td>'.e($item['position']).'</td></tr>')->join('');
    }

    /**
     * The other placements as the form shows them (`Renewal_Other`): two selects over the Groups
     * and the positions, the choice marked, then the two free texts.
     *
     * @param  list<array{group_id: int|null, position: string}>  $other
     * @param  list<string>  $texts
     * @param  Collection<int, array{id: int, name: string}>  $groups
     */
    public static function renderOther(array $other, array $texts, Collection $groups): string
    {
        $html = '';

        foreach ([0, 1] as $i) {
            $choice = $other[$i]['group_id'] ?? null;
            $position = $other[$i]['position'] ?? 'Member';
            $options = "<option value='0'>Select Committee</option>".$groups
                ->map(fn (array $group): string => "<option value='".$group['id']."'".($group['id'] === $choice ? ' selected ' : '').'>'.e($group['name']).'</option>')
                ->join('');
            $positions = collect(self::POSITIONS)
                ->map(fn (string $p): string => '<option'.($choice !== null && $p === $position ? ' selected ' : '').'>'.e($p).'</option>')
                ->join('');
            $html .= "<tr><td><select id='pl{$i}'>{$options}</select></td><td><select id='ch{$i}'>{$positions}</select></td></tr>";
        }

        foreach ([0, 1] as $i) {
            $html .= "<tr><td colspan='2'><input type='text' size='40' id='OtherCommittee_".($i + 1)."' placeholder='Other Placement / Committee' value='".e($texts[$i] ?? '')."' /></td></tr>";
        }

        return $html;
    }

    /**
     * The other placements as the record form prints them (`RecordForm_Other`): the chosen ones only.
     *
     * @param  list<array{group_id: int|null, position: string}>  $other
     * @param  list<string>  $texts
     * @param  Collection<int, array{id: int, name: string}>  $groups
     */
    public static function renderOtherRecordForm(array $other, array $texts, Collection $groups): string
    {
        $html = '';

        foreach ($other as $row) {
            $group = $groups->firstWhere('id', $row['group_id']);

            if ($group !== null) {
                $html .= '<tr><td>'.e($group['name']).'</td><td>'.e($row['position']).'</td></tr>';
            }
        }

        foreach ($texts as $text) {
            if (filled($text)) {
                $html .= '<tr><td colspan=2>'.e($text).'</td></tr>';
            }
        }

        return $html;
    }

    private static function xpath(?string $html): ?DOMXPath
    {
        if (blank($html)) {
            return null;
        }

        $document = new DOMDocument;
        // Table rows alone are not a document; wrap them, and read them as UTF-8.
        @$document->loadHTML('<?xml encoding="UTF-8"><table>'.$html.'</table>', LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
