# Step script for the "Sign up for a shift" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — she may take a seat on Docents Shifts,
# so the Sign up button renders on a shift with a free seat.
# Run: resources/help/screenshot-runner/run.sh sign-up-for-a-shift

persona amara.abara@dmv.test
start /groups/docents/scheduling

act showScheduleList 01             # the Scheduling tab: the list of schedules
act openCurrentMonthSchedule 02     # the agenda: the schedule's head and its first shifts
act showFirstOpenShift 03           # a shift with a free seat and its Sign up button
