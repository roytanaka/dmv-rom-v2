# Step script for the "What you can and cannot see about a Member" article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — a peer, so the first other Member in
# the roster (one she is not an officer over) shows the "Contact details are not
# available to you" note.
# Run: resources/help/screenshot-runner/run.sh what-you-can-see-about-a-member

persona amara.abara@dmv.test
start /directory

act openAnotherMembersProfile 01    # a peer's profile: name, photo, standing, Groups, contact restricted
