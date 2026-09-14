# Step script for the "Find a Member" article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — an ordinary reader of the roster.
# Run: resources/help/screenshot-runner/run.sh find-a-member

persona amara.abara@dmv.test
start /directory

nav /directory 01                   # the search box and the roster table
act openAnotherMembersProfile 02    # a Member's profile, opened from the roster's name link
