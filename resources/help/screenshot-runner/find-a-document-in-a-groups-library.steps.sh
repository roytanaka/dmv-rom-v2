# Step script for the "Find a document in a Group's library" article (#719, ADR-0025, ADR-0030).
#
# Persona: the library reader (PersonaCatalogue::LIBRARY_READER_EMAIL). He is outside Docents,
# and Docents shares its Natural History Folder with all members (#718 demo data). So the tab
# shows that one Folder, and the shots show the shared case the article's Note describes.
# Run: resources/help/screenshot-runner/run.sh find-a-document-in-a-groups-library

persona felix.andersson@dmv.test
start /groups/docents/documents

nav /groups/docents/documents 01   # the Documents tab: the Natural History Folder, readable by All members
act openFirstFolder 02             # inside Natural History: the path at the top, the Section Folders below
act openFirstFolder 03             # inside Dinosaurs: a tour Folder, then the data sheet with its Tags
nav /groups/docents/documents      # back to the library root
act openTagFilter 04               # the Tag list open: All documents, then the Group's Tags
nav /groups/docents/documents      # close the list
act filterByHighlightsTag 05       # filtered by Highlights: the tagged Documents from every readable Folder
