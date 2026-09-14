# Step script for the "Settings" overview article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — settings are the same for every Member.
# Run: resources/help/screenshot-runner/run.sh settings

persona amara.abara@dmv.test
start /settings/profile

nav /settings/profile 01    # the settings sidebar: Profile, Password, Skills
