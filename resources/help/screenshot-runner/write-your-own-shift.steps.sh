# Step script for the "Write your own shift" article (#590, ADR-0026 §1–§6).
#
# Persona: Oliver Bennett — a plain Member of Gallery Interpreters (he chairs Docents,
# but holds no role in GI). GI is the self-serve Group, so its Scheduling tab shows the
# Write my shift button and his own shifts carry Edit and Delete. Shoot on the GI demo
# month, reseeded first (#589), so the chrome is the GI roster, not a generic Group.
# Run: resources/help/screenshot-runner/run.sh write-your-own-shift
#
# Two shots are hand-made, not scripted: the station-clash confirm (03) needs a clash the
# runner cannot force, and the sign-out form (04) opens only near a shift's end. Shoot both
# by hand against the GI month and drop them in beside 01 and 02.

persona oliver.bennett@dmv.test
start /groups/gallery-interpreters/scheduling

act openCurrentMonthSchedule        # open the GI month (no shot)
act openWriteMyShiftDialog 01       # the Write my shift dialog: Station, Start time, Units, Objects
nav /groups/gallery-interpreters/scheduling   # reload to close the dialog
act openCurrentMonthSchedule        # reopen the month
act showMyWrittenShift 02           # a shift the Member wrote: its Edit and Delete controls
