# Step script for the "Cancel a sign-up" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — the seed seats her on an upcoming Docents
# Shift, so on that shift the Drop button shows in place of Sign up.
# Run: resources/help/screenshot-runner/run.sh cancel-a-sign-up

persona amara.abara@dmv.test
start /groups/docents/scheduling

act showScheduleList 01             # the Scheduling tab: the list of schedules
act openCurrentMonthSchedule 02     # the agenda: the schedule's head and its first shifts
act showMyShift 03                  # the shift with her name on it, showing the Drop button
