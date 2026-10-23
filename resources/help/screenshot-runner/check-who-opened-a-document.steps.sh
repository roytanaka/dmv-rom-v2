# Step script for the "Check who opened a Document" article (#754, ADR-0030).
#
# Persona: the President — super-tier, so the Download log item and page render.
# Run: resources/help/screenshot-runner/run.sh check-who-opened-a-document
#
# The demo seed writes no download rows. Open a few Documents first, as several
# Personas in more than one Group, so the log has rows to show. Shot 02 filters on
# the Docents Group by its seeded id (19); check it after a seeder change.

persona margaret.chen@dmv.test
start /dashboard

nav /officer/document-downloads 01            # the Download log: filters above, newest rows first
nav /officer/document-downloads?group=19 02   # filtered to the Docents Group
