# Step script for the "Pick an Audience" article (#527, ADR-0024 §5, §6).
#
# Persona: the Chair of Docents — an officer, so the menu offers the Group's roster and
# officer Audiences plus "Pick people…".
# Run: resources/help/screenshot-runner/run.sh pick-an-audience
#
# The Email menu (the Audience list) and the composer's To field are Radix overlays the
# runner cannot open; a developer captures the open menu and the To chips by hand.

persona oliver.bennett@dmv.test
start /groups/docents

nav /groups/docents 01   # the Group page: the "Email ▾" control that opens the Audience menu
