# Step script for the "Objects and off-site stations" article (#590, ADR-0026 §3, §4, §8).
# The three cards sit on the Group Settings tab (#608, ADR-0027 §2); the New shift dialog
# stays on the Scheduling tab.
#
# Persona: Margaret Chen — President, super-tier, and a member of Gallery Interpreters, so she
# administers the GI schedule and sees every self-serve card. No dedicated GI Scheduler Persona
# exists in the catalogue; the President is the catalogued account with schedule-admin reach on
# GI. Shoot on the GI demo month, reseeded first (#589), so the cards carry the GI vocabulary.
# Run: resources/help/screenshot-runner/run.sh objects-and-off-site-stations

persona margaret.chen@dmv.test
start /groups/gallery-interpreters/settings

act showSelfServeSettings 01        # the Self-serve shifts card: the toggle and Minutes per unit
act showObjects 02                  # the Objects card: add, rename, retire, and reorder
act showOffSiteStation 03           # the Shift kinds card: the CNE station with Off-site checked
nav /groups/gallery-interpreters/scheduling    # back to the Scheduling tab (no shot)
act openCurrentMonthSchedule        # open the GI month (no shot)
act openNewShiftDialog              # open the New shift dialog (no shot)
act chooseOffSiteKind 04            # the New shift dialog with the off-site station as the Kind
