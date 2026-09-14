# Step script for the "Run your Group's hours report" article (#526, ADR-0025).
#
# Persona: the Statistician of Docents — she may read the Group's hours report, so the
# Hours tab carries the report link, and the report, its fiscal-year picker, and the
# Print and Export CSV controls all render.
# Run: resources/help/screenshot-runner/run.sh run-your-groups-hours-report

persona ravi.singh@dmv.test
start /groups/docents/hours

nav /groups/docents/hours 01          # the Hours tab: the View the group hours report link above the entry form
nav /groups/docents/hours/report 02   # the report: the Member rows, the month columns, and the fiscal-year picker
nav /groups/docents/hours/month 03    # the By month view, reached from the links across the top
