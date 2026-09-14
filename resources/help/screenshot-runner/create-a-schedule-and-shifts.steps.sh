# Step script for the "Create a Schedule and its Shifts" article (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so New schedule, New shift,
# and Publish all render on the Scheduling tab and a Schedule's agenda. The demo seed
# drafts next month on Docents, empty, so the draft shots show a Schedule just made.
# Run: resources/help/screenshot-runner/run.sh create-a-schedule-and-shifts

persona james.tremblay@dmv.test
start /groups/docents/scheduling

act showNewScheduleButton 01        # the Scheduling tab: the New schedule button above the list
act openNewScheduleDialog 02        # the New schedule dialog: Name, Start date, End date, Description
nav /groups/docents/scheduling      # reload to close the dialog
act openDraftSchedule 03            # the draft Schedule: its Draft badge, Publish, and New shift
act openNewShiftDialog 04           # the New shift dialog: Start, End, Capacity, Kind, Audience
