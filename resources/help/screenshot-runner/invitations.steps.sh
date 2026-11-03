# Step script for the "What Invitations are for" overview (#910, ADR-0034).
#
# Persona: a Full-standing Docents member, so both tabs show. Run
# reply-to-an-invitation first, so My invitations holds a row.
# Run: resources/help/screenshot-runner/run.sh invitations

persona amara.abara@dmv.test
start /invitations

nav /invitations 01             # the Invitations page: My invitations and Hosting tabs, one row
