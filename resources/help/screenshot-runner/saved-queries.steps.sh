# Step script for the "Saved queries" article (#923, ADR-0038 §7).
#
# Persona: the Records steward (Priya Nair), a member of the Records Group, which stewards member
# administration. Only member-administration authority reaches these pages. Reseed first: the
# demo seed adds three queries.
# Run: resources/help/screenshot-runner/run.sh saved-queries

persona priya.nair@dmv.test
start /officer/members/queries

nav /officer/members/queries 01            # the index: three demo queries with Run, Edit, Rename, Delete
nav /officer/members/queries/create 02     # the form: name, match, one condition, columns, sort
nav /officer/members/queries/1 03          # a run: count, condition text, table with ticked boxes
