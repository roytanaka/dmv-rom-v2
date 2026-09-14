# Step script for the "Change your password" article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — the password form is the same for every Member.
# Run: resources/help/screenshot-runner/run.sh change-your-password

persona amara.abara@dmv.test
start /settings/password

nav /settings/password 01   # the password form: current, new, and confirm
