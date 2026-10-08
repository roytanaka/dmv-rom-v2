# Step script for the "Check who opened a Document" article (#754, ADR-0030).
#
# Persona: the President — super-tier, so the Download log item and page render.
# Run: resources/help/screenshot-runner/run.sh check-who-opened-a-document
#
# Open a few Documents first (as any persona), so the log has rows to show.

persona margaret.chen@dmv.test
start /dashboard

nav /officer/document-downloads 01   # the Download log: filters above, newest rows first
