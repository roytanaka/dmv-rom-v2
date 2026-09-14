# Step script for the "Export a report as CSV" article (#526, ADR-0025).
#
# Persona: the Statistician of Docents — she may read the Group's hours report, so the
# Export CSV control renders in the report toolbar.
# Run: resources/help/screenshot-runner/run.sh export-a-report-as-csv

persona ravi.singh@dmv.test
start /groups/docents/hours/report

nav /groups/docents/hours/report 01   # the report toolbar: the Print and Export CSV buttons beside the fiscal-year picker
