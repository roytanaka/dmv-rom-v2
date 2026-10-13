# Step script for the "Sign up for a group tour" article (#798, ADR-0032 §8, ADR-0025).
# Shot on the Docents Scheduling tab.
#
# Persona: a full-standing Docent. Shot 02 needs them on an upcoming group tour; if the seed left
# them off every one, sign them up first from shot 01's schedule.
# Run: resources/help/screenshot-runner/run.sh sign-up-for-a-group-tour

persona amara.abara@dmv.test
start /groups/docents/scheduling

act openGroupTourSchedule 01 # the month's group-tour Schedule: group tours with Sign up on open seats
act openSubstituteDialog 02  # the Substitute dialog: the member picker and Hand over seat
