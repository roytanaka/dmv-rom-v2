# Step script for the "Member lists and the Member record" article (#919, #917, ADR-0038 §3, §5).
#
# Persona: the Records steward (Priya Nair), a member of the Records Group, which stewards member
# administration. Only member-administration authority reaches these pages.
# Run: resources/help/screenshot-runner/run.sh member-lists-and-the-member-record

persona priya.nair@dmv.test
start /officer/members

nav /officer/members 01             # the Current list: tabs, search, rows with Edit and Photo
nav /officer/members/resigned 02    # the Resigned list: rows with Edit and Reinstate
