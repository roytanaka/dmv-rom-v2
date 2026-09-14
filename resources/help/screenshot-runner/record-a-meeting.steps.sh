# Step script for the "Record a meeting" article (#526, ADR-0025).
#
# Persona: the Secretary of the Executive committee — she records meetings, so New
# meeting and the Upcoming and Past lists render on the Meetings tab. Executive holds
# meetings, so the tab is present.
# Run: resources/help/screenshot-runner/run.sh record-a-meeting
#
# The New meeting dialog is a Radix overlay the runner cannot open; a developer
# captures that shot by hand against the live chrome.

persona elena.rossi@dmv.test
start /groups/executive/meetings

nav /groups/executive/meetings 01   # the Meetings tab: the New meeting button and the Upcoming and Past lists
