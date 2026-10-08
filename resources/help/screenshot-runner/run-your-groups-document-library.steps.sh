# Step script for the "Run your Group's Document library" article (#719, #729, ADR-0025, ADR-0030).
#
# Persona: the Docents Librarian (PersonaCatalogue::LIBRARIAN_EMAIL). The Docents root (#727
# demo data) has two Document categories, Data Sheets and Publications, and a file and a link
# under Other, so every Librarian control renders. No step saves, so the demo data stays as
# seeded. Reseed before shooting.
# Run: resources/help/screenshot-runner/run.sh run-your-groups-document-library

persona hannah.schmidt@dmv.test
start /groups/docents/documents

nav /groups/docents/documents 01   # the root as a Librarian: Categories button, upload Category, drop zone, Add link, New folder
act openCategoriesDialog 02        # the Categories dialog: Data Sheets and Publications with rename and delete, then New category
nav /groups/docents/documents      # close the dialog
act openDeleteCategoryDialog 03    # Delete Data Sheets? Its folders and files move to Other.
nav /groups/docents/documents      # close the dialog
act openNewFolderDialog 04         # the New folder dialog: Name, Who can read it, Category
nav /groups/docents/documents      # close the dialog
act openFolderEditDialog 05        # Exhibition's Edit folder dialog: Group members, Category Data Sheets
nav /groups/docents/documents      # close the dialog
act openDocumentMenu 06            # Docent handbook's actions menu under Other: Edit, Replace file, Move, Delete
nav /groups/docents/documents      # close the menu
act openMoveDocumentDialog 07      # the Move dialog: Move to the top level, Category No category
