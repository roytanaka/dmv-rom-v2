# Step script for the "Attach a file to an email" article (#527, ADR-0024 §6).
#
# Persona: the Chair of Docents — an officer, so a Broadcast reaches the Message step where
# "Add attachment" lives.
# Run: resources/help/screenshot-runner/run.sh attach-a-file
#
# The helper attaches a stand-in PDF through the hidden file input, as the file picker
# would. Nothing is sent.

persona oliver.bennett@dmv.test
start /groups/docents

act openWholeGroupComposer
act goToMessageStep
act writeSampleEmail
act attachSampleFile 01   # the Message step: the file's name and size under Attachments, with its ×
