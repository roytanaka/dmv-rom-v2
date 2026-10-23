# Step script for the "Send group tour emails" article (#799, ADR-0032 §9, ADR-0025).
# Shot on the Docents Scheduling tab.
#
# Persona: the Chair of Docents — the Chair implies Booker, so each group tour shows the
# Send request and Send confirmation buttons.
# Run: resources/help/screenshot-runner/run.sh send-group-tour-emails

persona oliver.bennett@dmv.test
start /groups/docents/scheduling

act openGroupTourSchedule 01 # the month's group-tour Schedule: each group tour with Send request and Send confirmation
