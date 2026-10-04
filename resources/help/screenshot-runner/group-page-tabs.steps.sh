# Step script for the "The tabs on a Group page" article (#525, ADR-0025).
#
# Persona: a Full-standing Docents member. Docents runs scheduling, so the Scheduling
# tab renders on its page; a program holds no meetings, so the Meetings tab is shot on
# DMV, the standing committee she also belongs to.
# Run: resources/help/screenshot-runner/run.sh group-page-tabs

persona amara.abara@dmv.test
start /groups/docents

nav /groups/docents 01             # the section tabs across the top, on Overview
nav /groups/docents/roster 02      # the Members tab: the roster
nav /groups/dmv/meetings 03        # the Meetings tab, on a Group that holds meetings
nav /groups/docents/scheduling 04  # the Scheduling tab
nav /groups/docents/hours 05       # the Hours tab
