# Step script for the "Send a Direct message to a Member" article (#527, ADR-0024 §6).
#
# Persona: a Full-standing Docents member — any Member may write a Direct message, so the
# "Message" button renders in another Member's profile header.
# Run: resources/help/screenshot-runner/run.sh send-a-direct-message
#
# The last step sends for real. See README.md, "Shots that send mail".

persona amara.abara@dmv.test
start /directory

act openAnotherMembersProfile 01   # a Member's profile: the "Message" button in the header
act openMessageComposer 02         # the Who step: the one recipient and the reply-address note
act goToMessageStep
act writeSampleEmail 03            # the Message step: subject and message filled in, Send live
act sendEmail 04                   # the Sent step: "Queued for 1 member."
