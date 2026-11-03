# Step script for the "Member lists and the Member record" article (#919, #917, ADR-0038 §3, §5).
#
# Persona: the Records steward (Priya Nair), a member of the Records Group, which stewards member
# administration. Only member-administration authority reaches these pages, and only a Member in
# the Current Category group can sign in. Reseed first: the demo seed writes the Records fields.
# Run: resources/help/screenshot-runner/run.sh member-lists-and-the-member-record

persona priya.nair@dmv.test
start /officer/members

nav /officer/members 01             # the Current list: tabs, search, rows with Edit and Photo
act openFirstMemberRecord 02        # the first Member's record: the Standing section and the Category history
nav /officer/members/resigned 03    # the Resigned list: rows with Edit and Reinstate
