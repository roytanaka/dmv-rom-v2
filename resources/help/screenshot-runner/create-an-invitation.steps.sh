# Step script for the "Create an Invitation" article (#910, ADR-0034).
#
# Persona: the Chair of Docents, a Member with Full access, so the Hosting tab renders.
# Run: resources/help/screenshot-runner/run.sh create-an-invitation
#
# The demo data has no Invitations, so this script makes the one the other Invitations
# scripts use. See README.md, "Shots that change the demo data", for the order.

persona oliver.bennett@dmv.test
start /invitations/hosting

nav /invitations/hosting 01     # the Hosting tab, empty, with New invitation
nav /invitations/create
act fillSampleInvitation 02     # the form filled in, with one Option
act saveInvitation 03           # the Hosting tab: the new Invitation as a Draft
