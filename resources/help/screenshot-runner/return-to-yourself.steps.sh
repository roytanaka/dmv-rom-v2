# Step script for the "Return to yourself after impersonating" article (#527, ADR-0009).
#
# Persona: the Support operator — the marker that shows the Role-switcher toolbar.
# Run: resources/help/screenshot-runner/run.sh return-to-yourself
#
# The helper becomes a member Persona first, so the toolbar shows its active state. The
# page is then reloaded, because the top-bar avatar keeps the operator's initials until a
# full load. The last step stops, so the operator ends the run as themself.
# Non-production only.

persona operator@dmv.test
start /dashboard

act becomeAmaraAbara
nav /dashboard 01         # reloaded as the Persona: the active toolbar, bottom right, with Switch and Stop
act openRoleSwitcher 02   # Switch open: the Persona list, to become someone else
nav /dashboard            # reload to close the list
act stopImpersonating     # back to the operator
