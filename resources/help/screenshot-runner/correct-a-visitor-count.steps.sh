# Step script for the "Correct a seat's visitor count" article (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so each signed-up seat carries
# the pencil that opens the correction form. Seat a Member on a recent, ended Docents
# Shift before the run, so the pencil and the count field have real chrome to show.
# Run: resources/help/screenshot-runner/run.sh correct-a-visitor-count
#
# The correction form opens from the pencil on a seat; a developer captures that shot
# by hand against the live chrome.

persona james.tremblay@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling 01   # the Scheduling tab: the schedule list
act openFirstSchedule 02            # a Schedule's agenda: a seat with the pencil to correct its count
