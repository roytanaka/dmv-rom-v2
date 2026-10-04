# Step script for the "Record your visitor count after a shift" article (#525, #656, #668, ADR-0025).
#
# Persona: a Full-standing Docents member — Docents collects a visitor count, and the
# seed seats her on a recent, ended Shift inside the recording window, so her own form
# opens in the Post-shift report on the My sign-ups panel.
# Shot 02 records the shift, so it changes the demo DB: shoot shifts-you-owe-a-number-for
# first, or reseed before shooting it again.
# Run: resources/help/screenshot-runner/run.sh record-your-visitor-count

persona amara.abara@dmv.test
start /groups/docents/scheduling

nav /groups/docents/scheduling
act showOwnPostShiftForm 01         # the Post-shift report on her shift: Visitors served, Comment (optional), Record shift
act recordOwnShift                  # record the shift with a count and a comment; it stays in My sign-ups until the next load
act showOwnSavedEntry 02            # the saved entry: Saved., the count, the comment preview, Last edited by, and Change
