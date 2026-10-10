# Step script for the "Keep your Group's Tours" article (#788, ADR-0033, ADR-0025). Shot on the
# Group Settings tab (ADR-0027 §2).
#
# Persona: the Chair of Docents — the Chair implies Vetting, so the Tours card renders on the
# Group Settings tab of a vetting Group. It shows only to a Vetting officer or Chair.
# Run: resources/help/screenshot-runner/run.sh tours-and-qualifications

persona oliver.bennett@dmv.test
start /groups/docents/settings

act showTours 01                # the Tours card: the Tour list with its controls and the add box
