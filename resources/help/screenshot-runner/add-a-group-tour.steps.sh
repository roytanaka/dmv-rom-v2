# Step script for the "Add a group tour" article (#795, ADR-0032 §1, §4, §5, ADR-0025).
# Shot on the Docents Scheduling tab.
#
# Persona: the Chair of Docents — the Chair implies Booker, so the "Add group tour" button renders
# on the Scheduling tab of a Group that runs bookings.
# Run: resources/help/screenshot-runner/run.sh add-a-group-tour

persona oliver.bennett@dmv.test
start /groups/docents/scheduling

act openAddGroupTour 01      # the Add a group tour dialog: client, tour, type, date, times, docents, visitors
act openGroupTourSchedule 02 # the month's group-tour Schedule: each group tour with its client half and order line
