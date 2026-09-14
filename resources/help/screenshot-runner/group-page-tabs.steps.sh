# Step script for the "The tabs on a Group page" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member — Docents runs meetings and scheduling,
# so every tab the article names renders on its page.
# Run: resources/help/screenshot-runner/run.sh group-page-tabs

persona amara.abara@dmv.test
start /groups/docents

nav /groups/docents 01             # the section tabs across the top, on Overview
nav /groups/docents/roster 02      # the Members tab: the roster
nav /groups/docents/scheduling 03  # the Scheduling tab
nav /groups/docents/hours 04       # the Hours tab
