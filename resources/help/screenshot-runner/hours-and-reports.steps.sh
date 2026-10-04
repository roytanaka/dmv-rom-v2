# Step script for the "Hours and reports" overview article (#526, ADR-0025).
#
# Persona: the Statistician of Docents — she may read the Group's hours report, so the
# fiscal-year matrix and totals render.
# Run: resources/help/screenshot-runner/run.sh hours-and-reports

persona ravi.singh@dmv.test
start /groups/docents/hours/report

nav /groups/docents/hours/report 01   # a Group's hours report: the fiscal-year matrix with the group totals
