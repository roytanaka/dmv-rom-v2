# Step script for the "Correct a seat's visitor count" article (#526, #656, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so every entry in a Post-shift
# report carries Change, which opens the correction form. The demo seed fills last month's
# Docents Schedule with worked tours and their filed counts, so there is an entry to show.
# Run: resources/help/screenshot-runner/run.sh correct-a-visitor-count

persona james.tremblay@dmv.test
start /groups/docents/scheduling

act openLastMonthSchedule           # open last month's Schedule, whose seats carry filed counts
act showPostShiftChange 01          # an ended Shift's Post-shift report: each entry with its count and Change
act openPostShiftCorrection 02      # the correction form open in the report, naming whose visitors they are
