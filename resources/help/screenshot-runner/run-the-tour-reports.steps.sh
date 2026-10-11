# Step script for the "Run the tour reports" article (#800, ADR-0032 §12, ADR-0025).
#
# Persona: the Statistician of Docents — she reads both reports and enters exhibition revenue, so
# the exhibition revenue field renders on Tour summary. Nothing is saved; the demo data is untouched.
# Run: resources/help/screenshot-runner/run.sh run-the-tour-reports

persona ravi.singh@dmv.test
start /groups/docents/hours

nav /groups/docents/hours/tour-summary 01   # Tour summary: the month picker, the exhibition revenue field, the type rows and the grand total
nav /groups/docents/hours/tour-detail 02    # Tour detail: each booking type split by tour, with a total per type
