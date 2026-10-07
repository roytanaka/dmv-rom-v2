# Step script for the "Run your Group's Document library" article (#719, ADR-0025, ADR-0030).
#
# Persona: the Docents Librarian (PersonaCatalogue::LIBRARIAN_EMAIL). The Docents library
# (#718 demo data) has two top-level Folders, root Documents, a link, and four Tags, so every
# Librarian control renders on the tab. No step saves, so the demo data stays as seeded.
# Run: resources/help/screenshot-runner/run.sh run-your-groups-document-library

persona hannah.schmidt@dmv.test
start /groups/docents/documents

nav /groups/docents/documents 01   # the tab as a Librarian: upload box, Add link, New folder, Manage tags, row actions
act openNewFolderDialog 02         # the New folder dialog: Name and Who can read it
nav /groups/docents/documents      # close the dialog
act openFolderEditDialog 03        # a top-level Folder's Edit dialog: Who can read it set to All members
nav /groups/docents/documents      # close the dialog
act openDocumentMenu 04            # a Document's actions menu: Edit, Replace file, Move, Delete
nav /groups/docents/documents      # close the menu
act openMoveDocumentDialog 05      # the Move dialog: the Move to list of Folders
nav /groups/docents/documents      # close the dialog
act openManageTagsDialog 06        # the Tags dialog: the Group's Tags with rename and delete, and New tag
nav /groups/docents/documents      # close the dialog
act openDocumentTagsDialog 07      # one Document's Tags dialog: a checkbox per Tag
