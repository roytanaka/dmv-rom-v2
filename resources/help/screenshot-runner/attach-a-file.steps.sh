# Step script for the "Attach a file to an email" article (#527, ADR-0024 §6).
#
# Persona: the Chair of Docents — an officer, so a Broadcast reaches the Message step where
# "Add attachment" lives.
# Run: resources/help/screenshot-runner/run.sh attach-a-file
#
# The Message step and its "Add attachment" control sit inside the composer sheet, a Radix
# overlay the runner cannot open; a developer captures the attachment shots by hand.

persona oliver.bennett@dmv.test
start /groups/docents

nav /groups/docents 01   # the Group page: the "Email ▾" control that opens the composer
