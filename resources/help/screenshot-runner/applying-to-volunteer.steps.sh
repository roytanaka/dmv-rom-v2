# Step script for the "Apply to volunteer" article (#930, #949, spec #914, ADR-0038 §8).
#
# No steps: every page this article shows is a guest page (/apply with its code entry, the
# form and its confirmation), and the runner always signs a Persona in, which the guest
# middleware sends away from them. Hand-shoot them signed out, after the Applicant Chair
# publishes a code (Officer Tools → Members → Applicants → Applicant code):
#   01 /apply with the code entry, 02 the form, 03 the confirmation.

persona amara.abara@dmv.test
start /dashboard
