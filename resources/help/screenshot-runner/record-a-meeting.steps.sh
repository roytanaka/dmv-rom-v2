# Step script for the "Record a meeting" article (#526, ADR-0025).
#
# Persona: the Secretary of the Executive committee — she records meetings, so New
# meeting and each card's Edit and Delete render on the Meetings tab. The demo seed
# puts one upcoming and one past meeting on Executive, so the tab shows both lists.
# Run: resources/help/screenshot-runner/run.sh record-a-meeting
#
# The New meeting dialog is taller than the 800 px viewport, so 02 is hand-shot at
# 1280 x 900 to keep its title and Save in frame. Re-shoot it the same way until the
# dialog scrolls.

persona elena.rossi@dmv.test
start /groups/executive/meetings

nav /groups/executive/meetings 01   # the Meetings tab: New meeting, then the Upcoming and Past cards
act openNewMeetingDialog 02         # the New meeting dialog: Title, Date and time, links, and Published
