# Step script for the "Find your Group's page" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — she belongs to Groups under My Groups,
# so the rail she sees matches the reader's.
# Run: resources/help/screenshot-runner/run.sh find-your-group

persona amara.abara@dmv.test
start /dashboard

nav /dashboard 01          # the rail's My Groups list, where a Group opens from
nav /groups/docents 02     # the opened Group page
