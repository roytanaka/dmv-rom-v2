# Step script for the "Renewing your membership" article (#941, spec #915, ADR-0038 §9).
#
# Persona: a Full-standing Docents member. Reseed first: the demo seed opens this year's Renewal.
# The Persona must need to renew (paid-through year before the renewal year). Steps 3 and 4 open
# only after the form is saved for the year, so shoot 01-02 first, save the form by hand, then
# shoot 03-05. The script itself never saves.
# Run: resources/help/screenshot-runner/run.sh renewing-your-membership

persona amara.abara@dmv.test
start /renew

nav /renew 01                # step 1: the code of conduct and its checkbox
nav /renew/form 02           # step 2: the renewal form
nav /renew/skills 03         # step 3: the skills picker
nav /renew/payment 04        # step 4: the fee, the ROM donation page link, the cheque text
nav /settings/profile 05     # My Profile: paid-through year, renewal year, "renewed, payment pending"
