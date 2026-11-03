# Step script for the "Reply to an Invitation" article (#910, ADR-0034).
#
# Persona: a Full-standing Docents member, a Guest on the sample Invitation. Run
# create-an-invitation, pick-your-guests and send-an-invitation first.
# Run: resources/help/screenshot-runner/run.sh reply-to-an-invitation

persona amara.abara@dmv.test
start /invitations

nav /invitations 01             # My invitations: the Invitation with No reply
act openFirstInvitation 02      # the Invitation: details, Options, Accept and Decline
act tickFirstInvitationOption
act acceptInvitation 03         # Your reply reads Accepted, with the Option ticked
