# Step script for the "Set a Reply for a Guest" article (#910, ADR-0034).
#
# Persona: the Chair of Docents, the Host of the sample Invitation.
# Run: resources/help/screenshot-runner/run.sh set-a-reply-for-a-guest

persona oliver.bennett@dmv.test
start /invitations/hosting

act openInvitationReplies
act openSetReplyDialog 01       # the Set reply dialog for the first Guest
