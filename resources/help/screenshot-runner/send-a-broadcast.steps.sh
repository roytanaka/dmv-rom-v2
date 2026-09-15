# Step script for the "Send a Broadcast to your Group" article (#527, ADR-0024 §6).
#
# Persona: the Chair of Docents — an officer of the Group, so the "Email ▾" control is live
# and the "Whole group" Audience resolves.
# Run: resources/help/screenshot-runner/run.sh send-a-broadcast
#
# The last step sends for real. See README.md, "Shots that send mail".

persona oliver.bennett@dmv.test
start /groups/docents

act openEmailMenu 01            # the Email menu open under the tab strip: each Audience and its count
act openWholeGroupComposer 02   # the Who step: Whole group, a name with an × for each Member
act goToMessageStep
act writeSampleEmail 03         # the Message step: subject and message filled in, Send live
act sendEmail 04                # the Sent step: "Queued for N members."
