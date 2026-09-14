# Step script for the "Set Reminders and the empty-desk alert for your Group" article
# (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so both settings cards render
# on the Scheduling tab's list view. They show only to a Scheduler or Chair.
# Run: resources/help/screenshot-runner/run.sh set-reminders-and-the-empty-desk-alert

persona james.tremblay@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling 01   # the Scheduling tab: the Shift reminders and Empty-desk alert cards
