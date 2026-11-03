# Step script for the "Pick your Guests" article (#910, ADR-0034).
#
# Persona: the Chair of Docents, the Host of the sample Invitation. Run
# create-an-invitation first. Docents holds amara.abara@dmv.test, who replies later.
# Run: resources/help/screenshot-runner/run.sh pick-your-guests

persona oliver.bennett@dmv.test
start /invitations/hosting

act openInvitationGuests 01     # the Guests page: Add a Member, Add a group, no Guests yet
act addDocentsAsGuests 02       # the guest list filled from Docents, with the total
