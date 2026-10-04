# Step script for the "Scheduling" overview article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — Docents publishes a Schedule with Shifts,
# so the list and the agenda both have real chrome to show.
# Run: resources/help/screenshot-runner/run.sh scheduling

persona amara.abara@dmv.test
start /groups/docents/scheduling

act showScheduleList 01             # the Scheduling tab: the list of schedules
act openCurrentMonthSchedule        # open the month's Schedule
act showUpcomingFullShift 02        # an upcoming day: seats taken against capacity, and a full shift says Full
