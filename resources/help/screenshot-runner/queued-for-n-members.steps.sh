# Step script for the "What \"queued for N Members\" means" article (#527, ADR-0024 §4, §6).
#
# Persona: the Chair of Docents — an officer, so a Broadcast reaches the Sent step that reads
# "Queued for N members."
# Run: resources/help/screenshot-runner/run.sh queued-for-n-members
#
# The last step sends for real, to the Group's officers. See README.md, "Shots that send mail".

persona oliver.bennett@dmv.test
start /groups/docents

act openOfficersComposer
act goToMessageStep
act writeSampleEmail
act sendEmail 01   # the Sent step: "Queued for N members." and the delivery note
