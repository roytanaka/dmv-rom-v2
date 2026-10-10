# Step script for the "Change or delete a group tour" article (#796, ADR-0032 §1, §3, §4, ADR-0025).
# Shot on the Docents Scheduling tab.
#
# Persona: the Chair of Docents — the Chair implies Booker, so each group tour carries Edit and
# Delete. Delete confirms in a native browser dialog, which the runner does not shoot.
# Run: resources/help/screenshot-runner/run.sh change-or-delete-a-group-tour

persona oliver.bennett@dmv.test
start /groups/docents/scheduling

act openGroupTourSchedule 01 # the month's group-tour Schedule: each group tour with Edit and Delete
act openEditGroupTour 02     # the Edit group tour dialog, filled from the group tour
