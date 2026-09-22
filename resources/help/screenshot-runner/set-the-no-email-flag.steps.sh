# Step script for the "Turn off all email to a Member" article (#564, #483, ADR-0024 §9).
#
# Persona: the Records steward (Priya Nair), a member of the Records Group, which stewards
# member administration. Only member-administration authority sees the No email control, so
# the shot must be taken as a Records steward, not a plain Member.
# Run: resources/help/screenshot-runner/run.sh set-the-no-email-flag

persona priya.nair@dmv.test
start /directory

act openAnotherMembersProfile        # open a Member's profile (no shot; the control is below the fold)
act showNoEmailControl 01            # the Member administration card: the No email checkbox
