# Step script for the "Update your profile and photo" article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — the profile form is the same for every Member.
# Run: resources/help/screenshot-runner/run.sh update-your-profile

persona amara.abara@dmv.test
start /settings/profile

nav /settings/profile 01    # the profile form: photo, name, email, contact details
