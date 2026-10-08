# Step script for the "Find a document in a Group's library" article (#719, #729, ADR-0025, ADR-0030).
#
# Persona: a full-standing Docents member. The Docents library (#727 demo data) has two root
# Document categories, Data Sheets and Publications, then Other. Its World Culture Folder has
# 7 categories and 13 Folders, and Ancient Egypt & Nubia below it holds three files. Reseed
# before shooting.
# Run: resources/help/screenshot-runner/run.sh find-a-document-in-a-groups-library

persona amara.abara@dmv.test
start /groups/docents/documents

nav /groups/docents/documents 01   # the root: breadcrumb, Category filter, then the Data Sheets section
act showOtherSection 02            # the root's Other section: Docent handbook.pdf and the ROM Collections Online link
act openWorldCultureFolder 03      # World Culture: the Folder header (7 categories, 13 folders), then the AAAP section
act openEgyptAndNubiaFolder 04     # Ancient Egypt & Nubia: no categories, one plain list of three PDFs
nav /groups/docents/documents      # back to the root
act openWorldCultureFolder         # World Culture again, for the filter
act openCategoryFilter 05          # the Category filter open: All categories, then World Culture's categories
act filterByEgyptAndNubia 06       # filtered by Egypt & Nubia: that one section, the header unchanged
