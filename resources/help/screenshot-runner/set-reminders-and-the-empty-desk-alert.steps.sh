# Step script for the "Set Reminders and the empty-desk alert for your Group" article
# (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so both settings cards render
# on the Scheduling tab's list view. They show only to a Scheduler or Chair.
# Run: resources/help/screenshot-runner/run.sh set-reminders-and-the-empty-desk-alert

persona james.tremblay@dmv.test
start /groups/docents/scheduling

act showShiftReminders 01           # the Shift reminders card: its checkbox and the days before a shift
act showEmptyDeskAlert 02           # the Empty-desk alert card: its checkbox, days ahead, and the kinds to watch
