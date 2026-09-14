# Step script for the "Read the Mail status page" article (#527, ADR-0024 §10).
#
# Persona: the President — super-tier, so the Mail status page renders.
# Run: resources/help/screenshot-runner/run.sh read-the-mail-status-page

persona margaret.chen@dmv.test
start /mail-status

nav /mail-status 01   # the Mail status page: the four values and any warning banners
