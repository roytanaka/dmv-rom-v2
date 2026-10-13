# Step script for the "Correct Earned on a group tour" article (#797, ADR-0032 §7, ADR-0025).
# Shot on the Docents Scheduling tab.
#
# Persona: the Chair of Docents — the Chair implies Statistician, so the pencil beside Earned
# renders on each group tour. The dialog is shot open, never saved, so the demo data is untouched.
# Run: resources/help/screenshot-runner/run.sh correct-earned-on-a-group-tour

persona oliver.bennett@dmv.test
start /groups/docents/scheduling

act openGroupTourSchedule 01 # the month's group-tour Schedule: each group tour's Earned line, marked worked out
act openCorrectEarned 02     # the Correct Earned dialog: the amount field, Save and Cancel
