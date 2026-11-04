# Step script for the "Renewing your membership" article (#941, #953, spec #915, ADR-0038 §9).
#
# Persona: a Full-standing Docents member. Reseed first: the demo seed opens this year's Renewal
# and leaves the Persona paid through last year, with last year's code of conduct and form.
# The script walks the Renewal as the reader does: it accepts the code of conduct and saves the
# form, so steps 3 and 4 open. A second run finds step 1 skipped, so reseed before you shoot again.
# Run: resources/help/screenshot-runner/run.sh renewing-your-membership

persona amara.abara@dmv.test
start /renew

nav /renew 01                # step 1: the code of conduct and its checkbox
act acceptCodeOfConduct 02   # step 2: the renewal form
act saveRenewalForm 03       # step 3: the skills picker
nav /renew/payment 04        # step 4: the fee, the ROM donation page link, the cheque text
nav /settings/profile        # My Profile
act showRomMembership 05     # its ROM membership block: paid-through year, renewal year, "renewed, payment pending"
