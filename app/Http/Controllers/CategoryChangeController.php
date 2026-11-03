<?php

namespace App\Http\Controllers;

use App\Actions\ChangeCategory;
use App\Enums\Category;
use App\Enums\DepartureReason;
use App\Http\Requests\DeleteCategoryChangeRequest;
use App\Http\Requests\ReinstateMemberRequest;
use App\Http\Requests\StoreCategoryChangeRequest;
use App\Models\CategoryChange;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * The Category history writes (#920, ADR-0038 §4): add an entry, remove a wrong one, Reinstate.
 * Each goes through {@see ChangeCategory}, the one path that moves a Member's Category.
 */
class CategoryChangeController extends Controller
{
    public function store(StoreCategoryChangeRequest $request, Member $member, ChangeCategory $changeCategory): RedirectResponse
    {
        $changeCategory->change(
            $member,
            Category::from($request->validated('category')),
            Carbon::parse($request->validated('date')),
            DepartureReason::tryFrom((string) $request->validated('departure_reason')),
            $request->user(),
        );

        return back();
    }

    public function destroy(DeleteCategoryChangeRequest $request, Member $member, CategoryChange $categoryChange, ChangeCategory $changeCategory): RedirectResponse
    {
        $changeCategory->remove($categoryChange);

        return back();
    }

    public function reinstate(ReinstateMemberRequest $request, Member $member, ChangeCategory $changeCategory): RedirectResponse
    {
        $changeCategory->reinstate($member, $request->user());

        return back();
    }
}
