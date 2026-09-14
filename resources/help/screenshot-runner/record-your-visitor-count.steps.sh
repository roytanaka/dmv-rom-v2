# Step script for the "Record your visitor count after a shift" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — Docents collects a visitor count, and the
# seed seats her on a recent, ended Shift inside the sign-out window, so the Visitors
# served box and the Sign out button render on the My sign-ups panel.
# Run: resources/help/screenshot-runner/run.sh record-your-visitor-count

persona amara.abara@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling 01   # the My sign-ups panel: a recent shift with the Visitors served box and Sign out
