# Step script for the "Track the Replies" article (#910, ADR-0034).
#
# Persona: the Chair of Docents, the Host of the sample Invitation. Run
# reply-to-an-invitation first, so the totals show an Accepted Reply and an Option.
# Run: resources/help/screenshot-runner/run.sh track-the-replies

persona oliver.bennett@dmv.test
start /invitations/hosting

act openInvitationReplies 01    # the Replies page: totals, then each Guest with Reply and Options
act openSetReplyDialog 02       # the Set reply dialog for the first Guest
