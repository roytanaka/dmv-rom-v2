# Step script for the "Manage your Group's booking types" article (#794, ADR-0032 §6, ADR-0025).
# Shot on the Group Settings tab (ADR-0027 §2).
#
# Persona: the Chair of Docents — the Chair implies Booker, so the Group tours card renders on the
# Group Settings tab of a Group that runs bookings. It shows only to a Booker or Chair.
# Run: resources/help/screenshot-runner/run.sh manage-your-groups-booking-types

persona oliver.bennett@dmv.test
start /groups/docents/settings

act showGroupTours 01        # the Group tours card: shift kind, Schedule name, the five booking types
act openAddBookingType 02    # the Add dialog: name, rate per visitor, rate per docent-hour
