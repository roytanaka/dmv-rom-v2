<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteBoardPositionRequest;
use App\Http\Requests\SaveBoardPositionRequest;
use App\Models\BoardPosition;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;

/**
 * The board positions on a Member record (#918, ADR-0038 §3): add, change and remove, each
 * counting as an edit of the record.
 */
class BoardPositionController extends Controller
{
    public function store(SaveBoardPositionRequest $request, Member $member): RedirectResponse
    {
        $member->boardPositions()->make($request->validated())
            ->forceFill(['created_by' => $request->user()->id, 'updated_by' => $request->user()->id])
            ->save();
        $member->markUpdatedBy($request->user());

        return back();
    }

    public function update(SaveBoardPositionRequest $request, Member $member, BoardPosition $boardPosition): RedirectResponse
    {
        $boardPosition->fill($request->validated())->forceFill(['updated_by' => $request->user()->id])->save();
        $member->markUpdatedBy($request->user());

        return back();
    }

    public function destroy(DeleteBoardPositionRequest $request, Member $member, BoardPosition $boardPosition): RedirectResponse
    {
        $boardPosition->delete();
        $member->markUpdatedBy($request->user());

        return back();
    }
}
