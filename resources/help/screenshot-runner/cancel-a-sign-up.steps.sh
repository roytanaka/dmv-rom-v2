# Step script for the "Cancel a sign-up" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — on a shift she holds a seat on, the
# Drop button shows in place of Sign up. Seat her on a current shift before the run.
# Run: resources/help/screenshot-runner/run.sh cancel-a-sign-up

persona amara.abara@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling 01   # the Scheduling tab: the list of schedules
act openFirstSchedule 02            # the agenda: a shift with her name on it, showing the Drop button
