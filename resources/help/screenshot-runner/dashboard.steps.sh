# Step script for the "Dashboard" overview article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — her Dashboard shows real Group tiles,
# so the "My Groups" section has something to point at.
# Run: resources/help/screenshot-runner/run.sh dashboard

persona amara.abara@dmv.test
start /dashboard

nav /dashboard 01           # the Dashboard: the My Groups tile grid
