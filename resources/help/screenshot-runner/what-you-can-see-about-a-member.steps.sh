# Step script for the "What you can and cannot see about a Member" article (#524, ADR-0025).
#
# Persona: a Full-standing Docents member — a peer, so a profile she is not an
# officer over shows the "Contact details are not available to you" note.
# Run: resources/help/screenshot-runner/run.sh what-you-can-see-about-a-member

persona amara.abara@dmv.test
start /directory

nav /members/2 01           # a peer's profile: name, photo, standing, Groups, contact restricted
                            # swap the id for a Member Amara is not an officer over
