# Step script for the "Reminders: what you get and when" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member.
# Run: resources/help/screenshot-runner/run.sh reminders
#
# A shift reminder is an email, not an in-app page, so this article has no chrome to
# shoot. There are no steps. If a sample reminder helps the reader, a developer adds a
# redacted screenshot of the email by hand — the runner cannot capture sent mail.

persona amara.abara@dmv.test
start /groups/docents/scheduling
