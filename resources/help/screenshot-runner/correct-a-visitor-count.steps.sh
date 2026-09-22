# Step script for the "Correct a seat's visitor count" article (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so each signed-up seat carries
# the pencil that opens the correction form. The demo seed fills last month's Docents
# Schedule with worked tours and their filed counts, so the pencil has a seat to show.
# Run: resources/help/screenshot-runner/run.sh correct-a-visitor-count

persona james.tremblay@dmv.test
start /groups/docents/scheduling

act openLastMonthSchedule           # open last month's Schedule, whose seats carry filed counts
act showCorrectionPencil 01         # an ended Shift: each seat with its count and pencil
act openCorrectionForm 02           # the correction form open under the Shift, naming whose seat it is
