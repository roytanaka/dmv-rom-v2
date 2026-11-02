# Step script for the "Email Accepted Guests" article (#910, ADR-0034).
#
# Persona: the Chair of Docents, the Host of the sample Invitation. Run
# reply-to-an-invitation first, so the Invitation has an Accepted Guest.
# Run: resources/help/screenshot-runner/run.sh email-accepted-guests

persona oliver.bennett@dmv.test
start /invitations/hosting

act openInvitationReplies
act openEmailAcceptedGuests 01  # the composer with the Accepted Guests in the To list
