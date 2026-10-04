# Step script for the "Manage your Group's roster" article (#526, ADR-0025).
#
# Persona: the Chair of Docents — she manages the roster, so Add member, the row menu,
# and Show past members all render on the Members tab.
# Run: resources/help/screenshot-runner/run.sh manage-your-groups-roster

persona oliver.bennett@dmv.test
start /groups/docents/roster

nav /groups/docents/roster 01       # the Members tab: Show past members, Add member, and the per-row menu
act openAddMemberDialog 02          # the Add member dialog: the search list, Standing, and Roles
nav /groups/docents/roster          # reload to close the dialog
act openRosterRowMenu 03            # a row's menu: Manage, Resign member, and Remove (added in error)
nav /groups/docents/roster          # reload to close the menu
act openManageMembershipDialog 04   # the Manage membership dialog: Standing, the leave dates, and Roles
