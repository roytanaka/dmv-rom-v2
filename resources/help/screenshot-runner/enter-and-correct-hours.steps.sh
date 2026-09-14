# Step script for the "Enter and correct hours" article (#526, ADR-0025).
#
# Persona: a Full-standing Docents member — she may enter hours on Docents, so the
# extra-hours entry form and her own records render on the Group's Hours tab.
# Run: resources/help/screenshot-runner/run.sh enter-and-correct-hours

persona amara.abara@dmv.test
start /groups/docents/hours

nav /groups/docents/hours 01   # the Hours tab: the entry form for this month and last, above the records table
