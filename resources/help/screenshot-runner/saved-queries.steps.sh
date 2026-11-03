# Step script for the "Saved queries" article (#923, #924, ADR-0038 §7).
#
# Persona: the Records steward (Priya Nair), a member of the Records Group, which stewards member
# administration. Only member-administration authority reaches these pages. Reseed first: the
# demo seed adds three queries, in this order, so their ids are 1 to 3.
# Do not select Send in the Email results dialog: it writes Deliveries.
# Run: resources/help/screenshot-runner/run.sh saved-queries

persona priya.nair@dmv.test
start /officer/members/queries

nav /officer/members/queries 01            # the index: three demo queries with Run, Edit, Rename, Delete
nav /officer/members/queries/3/edit 02     # the form: name, match, three conditions, columns, sort
nav /officer/members/queries/1 03          # a run: count, condition text, outputs, table with ticked boxes
nav /officer/members/queries/1/labels 04   # the labels page: Printer, Print, one sheet
nav /officer/members/queries/1             # back to the run
act openEmailResults 05                    # the Email results dialog: Send to, Note
