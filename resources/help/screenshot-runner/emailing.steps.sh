# Step script for the "Emailing" overview article (#527, ADR-0024, ADR-0025).
#
# Persona: the Chair of Docents — an officer, so the "Email ▾" control is live on the
# Group page and its Audiences resolve.
# Run: resources/help/screenshot-runner/run.sh emailing

persona oliver.bennett@dmv.test
start /groups/docents

nav /groups/docents 01   # the Group page: the "Email ▾" control in the sticky tab strip
