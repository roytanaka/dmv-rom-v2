<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteDocumentCategoryRequest;
use App\Http\Requests\StoreDocumentCategoryRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Models\DocumentCategory;
use App\Models\Group;
use App\Support\DocumentLibrary;
use Illuminate\Http\RedirectResponse;

/**
 * The Librarian's Document category writes (#724, spec #721, ADR-0030 §4), the Folder
 * pattern: store nests under the Group with the owning Folder (none for the root); rename and
 * delete bind the Document category by id. Authorized in the Form Requests through the
 * DocumentPolicy's `manage`. The sections they make are read on the Documents tab
 * ({@see DocumentLibrary}).
 */
class DocumentCategoryController extends Controller
{
    /**
     * Add a Document category to the root's list or a Folder's.
     */
    public function store(StoreDocumentCategoryRequest $request, Group $group): RedirectResponse
    {
        $group->documentCategories()->create($request->validated());

        return back();
    }

    /**
     * Rename a Document category.
     */
    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return back();
    }

    /**
     * Delete a Document category. Its Folders and Documents move to Other: the database sets
     * their `category_id` to null.
     */
    public function destroy(DeleteDocumentCategoryRequest $request, DocumentCategory $category): RedirectResponse
    {
        $category->delete();

        return back();
    }
}
