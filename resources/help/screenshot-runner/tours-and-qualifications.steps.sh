# Step script for the "Keep your Group's Tours and qualifications" article (#788, #789, ADR-0033,
# ADR-0025). Shot on the Group Settings tab (ADR-0027 §2), the two qualification screens and the
# Tours tab (#792).
#
# Persona: the Chair of Docents — the Chair implies Vetting, so the Tours card renders on the
# Group Settings tab of a vetting Group, and the qualification screens open. Both show only to a
# Vetting officer or Chair.
# Run: resources/help/screenshot-runner/run.sh tours-and-qualifications

persona oliver.bennett@dmv.test
start /groups/docents/settings

act showTours 01                 # the Tours card: the Tour list with its controls and the add box
act openTourQualifications 02    # the by-Tour screen for Dinosaurs: qualified and inactive Members
act openMemberQualifications 03  # the by-Member screen: the Tours one Member gives
act openAddQualification 04      # the Add dialog: the Tour picker and today's Last vet date
nav /groups/docents/tours 05     # the Tours tab (#792): each active Tour, who gives it, open-to-all marked
# Signing up for, placing on and changing a Tour (#790, #791) is shot from the Scheduling tab in a
# later pass; the draft article carries no images yet.
