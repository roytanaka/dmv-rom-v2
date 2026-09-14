# Step script for the "Assign a Member to a Shift" article (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so the Place a member control
# renders on each shift in a Schedule's agenda.
# Run: resources/help/screenshot-runner/run.sh assign-a-member-to-a-shift
#
# The member-picker dialog is a Radix overlay the runner cannot open; a developer
# captures that shot by hand against the live chrome.

persona james.tremblay@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling 01   # the Scheduling tab: the schedule list
act openFirstSchedule 02            # a Schedule's agenda: a shift with its Place a member control
