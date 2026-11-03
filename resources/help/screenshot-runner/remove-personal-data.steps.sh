# Step script for the "Remove personal data" article (#921, ADR-0038 §6).
#
# Persona: the Records steward (Priya Nair), a member of the Records Group, which stewards member
# administration. Only member-administration authority reaches these pages.
# Run: resources/help/screenshot-runner/run.sh remove-personal-data

persona priya.nair@dmv.test
start /officer/members

nav /officer/members 01                        # the Current list with the Remove personal data button
nav /officer/members/remove-personal-data 02   # the year, the preview counts and the typed-count field
nav /officer/members/deceased 03               # the Deceased list: rows with Edit and Delete
