# Step script for the "Manage your Group's roster" article (#526, ADR-0025).
#
# Persona: the Chair of Docents — she manages the roster, so Add member, the row menu,
# and Show past members all render on the Members tab.
# Run: resources/help/screenshot-runner/run.sh manage-your-groups-roster
#
# The Add member and Manage membership dialogs are Radix overlays the runner cannot
# open; a developer captures those shots by hand against the live chrome.

persona oliver.bennett@dmv.test
start /groups/docents/roster

nav /groups/docents/roster 01   # the Members tab: the roster with Add member and the per-row menu
