# Step script for the "Assign a Stewardship" article (#846, ADR-0036 §7).
#
# Persona: the President — super-tier, so DMV Settings shows in Officer Tools and the
# Stewardships section opens.
# Run: resources/help/screenshot-runner/run.sh assign-a-stewardship
#
# The preview step only ticks a box; nothing is saved, so the demo data stays as seeded.

persona margaret.chen@dmv.test
start /dashboard

nav /officer/settings/stewardships 01   # both cards: Member administration held by Records, Org-wide sender
act showOrgWideSender 02                # the Org-wide sender card: holders, last change, tick rows
act previewOrgWideSender 03             # one more Group ticked: the Gain line lists its Members
