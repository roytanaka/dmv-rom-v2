# Step script for the "Add or remove many Shifts at once" article (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so the Bulk shifts control
# renders on an open Schedule's agenda. The demo seed drafts next month on Docents,
# empty, the Schedule a Scheduler fills in bulk.
# Run: resources/help/screenshot-runner/run.sh add-or-remove-many-shifts

persona james.tremblay@dmv.test
start /groups/docents/scheduling

act openDraftSchedule 01            # the draft Schedule's agenda: the Bulk shifts control
act openBulkShiftsDialog 02         # the Bulk-create shifts dialog: weekdays, times, dates, and both verbs
