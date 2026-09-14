# Step script for the "Send a Direct message to a Member" article (#527, ADR-0024 §6).
#
# Persona: a Full-standing Docents member — any Member may write a Direct message, so the
# "Message" button renders in another Member's profile header.
# Run: resources/help/screenshot-runner/run.sh send-a-direct-message
#
# The composer sheet the Message button opens is a Radix overlay the runner cannot open;
# a developer captures the Who → Message → Sent steps by hand against the live chrome.

persona amara.abara@dmv.test
start /directory

nav /directory 01     # the Directory: pick the Member to write to
nav /members/2 02     # a Member's profile — the "Message" button in the header (swap the id for any seeded Member)
