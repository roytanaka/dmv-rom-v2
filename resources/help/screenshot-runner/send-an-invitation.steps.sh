# Step script for the "Send an Invitation" article (#910, ADR-0034).
#
# Persona: the Chair of Docents, the Host of the sample Invitation. Run
# create-an-invitation and pick-your-guests first.
# Run: resources/help/screenshot-runner/run.sh send-an-invitation
#
# The Sent step sends for real. See README.md, "Shots that send mail".

persona oliver.bennett@dmv.test
start /invitations/hosting

act openInvitationSend 01       # the Who step: All Guests, a name for each Guest
act goToMessageStep 02          # the Message step: the prefilled subject and message
act sendEmail 03                # the Sent step
nav /invitations/hosting 04     # the Hosting tab: the Invitation is Open, with Re-send
