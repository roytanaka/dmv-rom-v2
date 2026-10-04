# Step script for the "Change your language" article (PRD #516, ADR-0025).
#
# Persona: a Full-standing Docents member — a member task, no role needed.
# Run: resources/help/screenshot-runner/run.sh change-your-language
#
# This file is sourced by the runner. It declares the Persona and the opening
# page, then lists steps in order. `nav` loads a page; `act` runs one in-page
# helper (see page-helpers.js). Each step ends in a two-digit shot name.
#
# The second shot is the one place the runner leaves English chrome: this
# article is about the French switch, so showing the French result is the point.
# Every other article stays English-only.

persona amara.abara@dmv.test
start /dashboard

act openLanguageSwitcher 01    # the locale switcher open, EN and FR listed
nav /fr/tableau-de-bord 02     # the same page under French chrome
