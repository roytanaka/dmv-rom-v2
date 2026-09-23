# Step script for the "Manage your Group's shift kinds" article (#567, ADR-0025). Shot on
# the Group Settings tab (#608, ADR-0027 §2).
#
# Persona: the Scheduler of Docents — a schedule admin, so the Shift kinds card renders on
# the Group Settings tab. It shows only to a Scheduler or Chair.
# Run: resources/help/screenshot-runner/run.sh manage-your-groups-shift-kinds

persona james.tremblay@dmv.test
start /groups/docents/settings

act showShiftKinds 01           # the Shift kinds card: the list of kinds and the add box
