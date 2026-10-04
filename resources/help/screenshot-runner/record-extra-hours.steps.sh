# Step script for the "Record extra hours for a month" article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — she may enter hours on Docents, so the
# entry form (gated by canEnter) renders on the Group's Hours tab.
# Run: resources/help/screenshot-runner/run.sh record-extra-hours

persona amara.abara@dmv.test
start /groups/docents/hours

nav /groups/docents/hours 01    # the Hours tab: the extra-hours entry form, this month and last
