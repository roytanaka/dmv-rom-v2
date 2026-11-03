# Step script for the "Apply to volunteer" article (#930, spec #914, ADR-0038 §8).
#
# No steps: every page this article shows is a guest page (the sign-in page's code entry,
# /apply and its confirmation), and the runner always signs a Persona in, which the guest
# middleware sends away from them. Hand-shoot them signed out, after the Applicant Chair
# publishes a code (Officer Tools → Members → Applicants → Applicant code):
#   01 the sign-in page with the code entry, 02 the form, 03 the confirmation.

persona amara.abara@dmv.test
start /dashboard
