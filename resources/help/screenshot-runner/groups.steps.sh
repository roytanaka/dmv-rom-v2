# Step script for the "Groups" overview article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — her rail shows My Groups and Browse
# Groups, and she can open a Group page, so the overview has real chrome to point at.
# Run: resources/help/screenshot-runner/run.sh groups

persona amara.abara@dmv.test
start /dashboard

nav /dashboard 01          # the rail: My Groups and Browse Groups down the left
nav /groups/docents 02     # a Group page: the banner and the section tabs across the top
