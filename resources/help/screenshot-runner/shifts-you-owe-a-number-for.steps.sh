# Step script for the "The shifts you still owe a number for" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — the seed leaves her a recent, ended Shift
# with no visitor count, so it surfaces on the My sign-ups panel at the top of the tab.
# Run: resources/help/screenshot-runner/run.sh shifts-you-owe-a-number-for

persona amara.abara@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling 01   # the My sign-ups panel at the top: shifts still owed a number
