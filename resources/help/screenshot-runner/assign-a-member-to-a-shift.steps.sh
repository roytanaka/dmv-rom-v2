# Step script for the "Assign a Member to a Shift" article (#526, ADR-0025).
#
# Persona: the Scheduler of Docents — a schedule admin, so the Place a member control
# renders on each shift in a Schedule's agenda.
# Run: resources/help/screenshot-runner/run.sh assign-a-member-to-a-shift

persona james.tremblay@dmv.test
start /groups/docents/scheduling

act openCurrentMonthSchedule        # open the month's Schedule, where seats are still free
act showUpcomingPlaceAMember 01     # an upcoming Agenda Shift, under its day heading, with its Place a member control
act openPlaceAMemberDialog 02       # the picker: the search box above the Group's placeable Members
