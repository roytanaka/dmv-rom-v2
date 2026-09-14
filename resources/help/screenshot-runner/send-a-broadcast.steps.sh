# Step script for the "Send a Broadcast to your Group" article (#527, ADR-0024 §6).
#
# Persona: the Chair of Docents — an officer of the Group, so the "Email ▾" control is live
# and the "Whole group" Audience resolves.
# Run: resources/help/screenshot-runner/run.sh send-a-broadcast
#
# The Email menu and the composer sheet are Radix overlays the runner cannot open; a
# developer captures the Audience menu and the Who → Message → Sent steps by hand.

persona oliver.bennett@dmv.test
start /groups/docents

nav /groups/docents 01   # the Group page: the "Email ▾" control in the sticky tab strip
