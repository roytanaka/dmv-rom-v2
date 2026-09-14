# Step script for the "Create a Schedule and its Shifts" article (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so New schedule, New shift,
# and Publish all render on the Scheduling tab and a Schedule's agenda.
# Run: resources/help/screenshot-runner/run.sh create-a-schedule-and-shifts
#
# The New schedule and New shift dialogs are Radix overlays the runner cannot open;
# a developer captures those two shots by hand against the live chrome.

persona james.tremblay@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling 01   # the Scheduling tab: the schedule list and the New schedule button
act openFirstSchedule 02            # a Schedule's agenda: the New shift and Publish controls
