# Step script for the "Manage your Group's shift kinds" article (#567, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so the Shift kinds card renders on
# the Scheduling tab's list view. It shows only to a Scheduler or Chair.
# Run: resources/help/screenshot-runner/run.sh manage-your-groups-shift-kinds

persona james.tremblay@dmv.test
start /groups/docents/scheduling

act showShiftKinds 01           # the Shift kinds card: the list of kinds and the add box
