# Step script for the "My Hours" overview article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — she has hours in real Groups, so the
# per-Group cards and the year-to-date total render.
# Run: resources/help/screenshot-runner/run.sh my-hours

persona amara.abara@dmv.test
start /hours

nav /hours 01               # My Hours: the fiscal-year picker and the per-Group cards
