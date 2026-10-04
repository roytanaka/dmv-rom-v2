# Step script for the "Read your hours for the fiscal year" article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — she has hours across Groups, so the
# per-Group cards and the Year to date row render.
# Run: resources/help/screenshot-runner/run.sh read-your-hours

persona amara.abara@dmv.test
start /hours

nav /hours 01               # the fiscal-year picker and a Group card, month by month
