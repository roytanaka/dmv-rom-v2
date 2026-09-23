# Step script for the "Getting started" overview article (PRD #516, ADR-0025).
#
# Persona: a Full-standing Docents member — her rail shows real Groups, so the
# "The Left Side Bar" and "Your Groups" sections of the article have something to point at.
# Run: resources/help/screenshot-runner/run.sh getting-started
#
# This file is sourced by the runner. It declares the Persona and the opening
# page, then lists steps in order. `nav` loads a page; `act` runs one in-page
# helper (see page-helpers.js). Each step ends in a two-digit shot name.

persona amara.abara@dmv.test
start /dashboard

nav /dashboard 01           # the dashboard: the top bar and the rail both in view
act highlightHelpLink 02    # the "?" in the top bar, highlighted
