# Step script for the "Add or remove many Shifts at once" article (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so the Bulk shifts control
# renders on an open Schedule's agenda.
# Run: resources/help/screenshot-runner/run.sh add-or-remove-many-shifts
#
# The Bulk-create shifts dialog is a Radix overlay the runner cannot open; a
# developer captures that shot by hand against the live chrome.

persona james.tremblay@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling 01   # the Scheduling tab: the schedule list
act openFirstSchedule 02            # a Schedule's agenda: the Bulk shifts control
