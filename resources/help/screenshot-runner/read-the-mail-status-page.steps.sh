# Step script for the "Read the Mail status page" article (#527, ADR-0024 §10).
#
# Persona: the President — super-tier, so the Mail status item and page render.
# Run: resources/help/screenshot-runner/run.sh read-the-mail-status-page
#
# Drain the queue just before, so the page shows a fresh scheduler time and a recent send
# with no warning. See README.md, "Shots that send mail".

persona margaret.chen@dmv.test
start /dashboard

act openAccountMenu 01   # the avatar menu in the top bar: Mail status above Log out
nav /mail-status 02      # the Mail status page: the four values, no warnings
