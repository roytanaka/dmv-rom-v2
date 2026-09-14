# Step script for the "What \"queued for N Members\" means" article (#527, ADR-0024 §4, §6).
#
# Persona: the Chair of Docents — an officer, so a Broadcast reaches the Sent step that reads
# "Queued for N Members".
# Run: resources/help/screenshot-runner/run.sh queued-for-n-members
#
# The Sent step sits inside the composer sheet, a Radix overlay the runner cannot open; a
# developer captures the "Queued for N Members" shot by hand after sending a test Broadcast.

persona oliver.bennett@dmv.test
start /groups/docents

nav /groups/docents 01   # the Group page: the "Email ▾" control where a send begins
