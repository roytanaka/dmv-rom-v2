# Step script for the "Remove personal data" article (#921, #932, ADR-0038 §6).
#
# Persona: the Records steward (Priya Nair), a member of the Records Group, which stewards member
# administration. Only member-administration authority reaches these pages. Reseed first: the
# demo seed adds a Resigned and a Deceased Member whose status dates fall before the default year.
# The script stops at the typed total and the typed last name. It never runs the removal or the
# Delete: both change the demo data for good.
# Run: resources/help/screenshot-runner/run.sh remove-personal-data

persona priya.nair@dmv.test
start /officer/members/remove-personal-data

act typeRemovalTotal 01            # the year, the preview counts with Total, the total typed back
nav /officer/members/deceased 02   # the Deceased list: rows with Edit and Delete
act openFirstDeleteDialog 03       # the Delete dialog with the last name typed back
